package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"sort"
	"sync"
	"sync/atomic"
	"time"
)

// logMatch is an accepted entry waiting to be fully parsed and printed.
type logMatch struct {
	raw  []byte
	off  int // byte offset in the mmap data (mmap path)
	line int // 0-based start line
}

type segment struct{ start, end int }

// segmentBounds splits data[lo:hi] into entry-aligned segments in scan order.
// Segments grow geometrically so that the first page touches only a small part
// of the file while deep scans still get large, parallel-friendly segments.
func segmentBounds(data []byte, lo, hi int, asc bool, isNewEntry func([]byte) bool) []segment {
	const firstSize = 256 * 1024
	const maxSize = 64 * 1024 * 1024

	var out []segment
	size := firstSize
	if asc {
		s := lo
		for s < hi {
			p := s + size
			if p >= hi {
				out = append(out, segment{s, hi})
				break
			}
			b := entryStartAtOrAfter(data, p, hi, isNewEntry)
			out = append(out, segment{s, b})
			s = b
			if size < maxSize {
				size *= 2
			}
		}
		return out
	}

	e := hi
	for e > lo {
		p := e - size
		if p <= lo {
			out = append(out, segment{lo, e})
			break
		}
		b := entryStartAtOrAfter(data, p, e, isNewEntry)
		if size < maxSize {
			size *= 2
		}
		if b >= e {
			// No entry starts in this window (huge multi-line entry): widen it.
			if size >= maxSize {
				size *= 2
			}
			continue
		}
		out = append(out, segment{b, e})
		e = b
	}
	return out
}

// collectMmap scans segments in parallel and returns up to need matches in output order.
func collectMmap(data []byte, lo, hi int, isNewEntry func([]byte) bool, asc bool, need int, accept func([]byte) bool) []logMatch {
	segs := segmentBounds(data, lo, hi, asc, isNewEntry)
	n := len(segs)
	if n == 0 {
		return nil
	}

	results := make([][]logMatch, n)
	done := make([]chan struct{}, n)
	for i := range done {
		done[i] = make(chan struct{})
	}

	var next atomic.Int64
	var cutoff atomic.Int64
	cutoff.Store(int64(n))

	workers := runtime.NumCPU()
	if workers > n {
		workers = n
	}

	var wg sync.WaitGroup
	for w := 0; w < workers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			defer guardMmap()()
			for {
				k := int(next.Add(1) - 1)
				if k >= n {
					return
				}
				if int64(k) > cutoff.Load() {
					close(done[k])
					continue
				}
				seg := segs[k]
				chunk := data[seg.start:seg.end]

				var res []logMatch
				if asc {
					scanEntries(chunk, isNewEntry, func(e []byte, off int) bool {
						if int64(k) > cutoff.Load() {
							return false
						}
						if accept(e) {
							res = append(res, logMatch{raw: e, off: seg.start + off})
							if len(res) >= need {
								return false
							}
						}
						return true
					})
				} else {
					// Keep only the last `need` matches of the segment (newest entries).
					var ring []logMatch
					pos := 0
					scanEntries(chunk, isNewEntry, func(e []byte, off int) bool {
						if int64(k) > cutoff.Load() {
							return false
						}
						if accept(e) {
							m := logMatch{raw: e, off: seg.start + off}
							if len(ring) < need {
								ring = append(ring, m)
							} else {
								ring[pos] = m
								pos = (pos + 1) % need
							}
						}
						return true
					})
					res = make([]logMatch, len(ring))
					for i := range ring {
						res[i] = ring[(pos+len(ring)-1-i)%len(ring)]
					}
				}
				results[k] = res
				close(done[k])
			}
		}()
	}

	var out []logMatch
	for k := 0; k < n; k++ {
		<-done[k]
		out = append(out, results[k]...)
		if len(out) >= need {
			cutoff.Store(int64(k))
			out = out[:need]
			break
		}
	}
	wg.Wait()
	return out
}

// collectStream reads entries forward from a stream (gzip/remote) and returns up to
// need matches in output order. For desc only the last `need` matches are kept.
func collectStream(r io.Reader, isNewEntry func([]byte) bool, asc bool, need int, accept func([]byte) bool) ([]logMatch, error) {
	var ring []logMatch
	pos := 0
	err := streamEntries(r, isNewEntry, func(e []byte, startLine int) bool {
		if !accept(e) {
			return true
		}
		m := logMatch{raw: e, off: -1, line: startLine}
		if asc {
			ring = append(ring, m)
			return len(ring) < need
		}
		if len(ring) < need {
			ring = append(ring, m)
		} else {
			ring[pos] = m
			pos = (pos + 1) % need
		}
		return true
	})
	if asc {
		return ring, err
	}
	out := make([]logMatch, len(ring))
	for i := range ring {
		out[i] = ring[(pos+len(ring)-1-i)%len(ring)]
	}
	return out, err
}

// assignLineNumbers converts byte offsets into 0-based line numbers with a single pass
// over the data up to the last needed offset.
func assignLineNumbers(data []byte, matches []logMatch) {
	if len(matches) == 0 {
		return
	}
	idx := make([]int, len(matches))
	for i := range idx {
		idx[i] = i
	}
	sort.Slice(idx, func(a, b int) bool { return matches[idx[a]].off < matches[idx[b]].off })

	prev := matches[idx[0]].off
	count := countLines(data[:prev])
	for _, i := range idx {
		off := matches[i].off
		count += bytes.Count(data[prev:off], []byte{'\n'})
		prev = off
		matches[i].line = count
	}
}

func runLogs(cfg RemoteConfig, parser LogParser, limit, offset int, cursor, level, levels, channel, search string, searchRegex, searchCaseSensitive bool, dateFrom, dateTo, sortDir string) {
	if limit <= 0 {
		limit = 50
	}
	if offset < 0 {
		offset = 0
	}
	asc := sortDir == "asc"

	var cursorTime time.Time
	if cursor != "" {
		cursorTime, _ = time.Parse(time.RFC3339Nano, cursor)
		if cursorTime.IsZero() {
			cursorTime = parseTimestamp(cursor)
		}
	}

	f, err := newEntryFilter(parser, level, levels, channel, search, searchRegex, searchCaseSensitive, dateFrom, dateTo)
	if err != nil {
		fail("%v", err)
	}

	fileName := stripGzExt(filepath.Base(cfg.FilePath))
	isNewEntry := parser.IsNewEntry
	need := offset + limit

	accept := func(line []byte) bool {
		entry := f.match(line, fileName)
		if entry == nil {
			return false
		}
		ok := true
		if !cursorTime.IsZero() {
			if asc {
				ok = entry.Time.After(cursorTime)
			} else {
				ok = entry.Time.Before(cursorTime)
			}
		}
		PutEntry(entry)
		return ok
	}

	file, _, err := OpenLogFile(cfg)
	if err != nil {
		fail("Error opening file: %v", err)
	}
	defer file.Close()

	var mmapData []byte
	if cfg.Host == "" && !isGzipFile(cfg.FilePath) {
		if osF, ok := file.(*os.File); ok {
			if data, mErr := Mmap(osF); mErr == nil && len(data) > 0 {
				mmapData = data
				defer Munmap(mmapData)
			}
		}
	}

	var matches []logMatch
	if mmapData != nil {
		defer guardMmap()()
		lo, hi := searchRange(mmapData, parser, f, cursorTime, asc)
		matches = collectMmap(mmapData, lo, hi, isNewEntry, asc, need, accept)
	} else {
		var reader io.Reader = file
		if cfg.Host == "" && isGzipFile(cfg.FilePath) {
			gr, gErr := newGzipReader(file)
			if gErr != nil {
				fail("Error reading gzip file: %v", gErr)
			}
			defer gr.Close()
			reader = gr
		}
		var sErr error
		matches, sErr = collectStream(reader, isNewEntry, asc, need, accept)
		if sErr != nil {
			fail("Error reading file: %v", sErr)
		}
	}

	if len(matches) <= offset {
		return
	}
	matches = matches[offset:]
	if mmapData != nil {
		assignLineNumbers(mmapData, matches)
	}

	w := bufio.NewWriterSize(os.Stdout, 64*1024)
	defer w.Flush()
	enc := json.NewEncoder(w)
	for _, m := range matches {
		entry := parser.Parse(m.raw, fileName, true, true)
		if entry == nil {
			continue
		}
		entry.LineNumber = m.line
		enc.Encode(entry)
		PutEntry(entry)
	}
}
