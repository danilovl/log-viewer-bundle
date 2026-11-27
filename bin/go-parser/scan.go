package main

import (
	"bufio"
	"bytes"
	"errors"
	"io"
	"runtime"
	"sync"
)

const (
	// maxEntrySize caps the size of a single (multi-line) entry passed to a parser.
	maxEntrySize = 10 * 1024 * 1024
	// maxStreamLine caps a single physical line read from a stream (gzip/remote).
	maxStreamLine = 10 * 1024 * 1024
)

// capEntry truncates an oversized entry at the first line end after maxEntrySize,
// matching the historical "stop appending lines once 10MB is reached" behaviour.
func capEntry(entry []byte) []byte {
	if len(entry) <= maxEntrySize {
		return entry
	}
	idx := bytes.IndexByte(entry[maxEntrySize:], '\n')
	if idx == -1 {
		return entry
	}
	cut := maxEntrySize + idx
	if cut > 0 && entry[cut-1] == '\r' {
		cut--
	}
	return entry[:cut]
}

// stripInnerCR removes the '\r' of every "\r\n" inside a multi-line entry.
func stripInnerCR(entry []byte) []byte {
	if bytes.IndexByte(entry, '\r') == -1 {
		return entry
	}
	out := make([]byte, 0, len(entry))
	for len(entry) > 0 {
		nl := bytes.IndexByte(entry, '\n')
		if nl == -1 {
			out = append(out, entry...)
			break
		}
		line := entry[:nl]
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}
		out = append(out, line...)
		out = append(out, '\n')
		entry = entry[nl+1:]
	}
	return out
}

// scanEntries walks data line by line and calls fn for every complete log entry.
// Entries are sub-slices of data (zero-copy) unless a multi-line entry contains
// "\r\n" line endings. off is the entry's start offset inside data.
// Returning false from fn stops the scan.
func scanEntries(data []byte, isNewEntry func([]byte) bool, fn func(entry []byte, off int) bool) bool {
	start, end := -1, 0
	multi := false

	emit := func() bool {
		entry := data[start:end]
		if multi {
			entry = stripInnerCR(capEntry(entry))
		}
		return fn(entry, start)
	}

	pos := 0
	for pos < len(data) {
		lineEnd := len(data)
		next := len(data)
		if nl := bytes.IndexByte(data[pos:], '\n'); nl != -1 {
			lineEnd = pos + nl
			next = lineEnd + 1
		}
		le := lineEnd
		if le > pos && data[le-1] == '\r' {
			le--
		}

		if isNewEntry(data[pos:le]) {
			if start >= 0 && end > start {
				if !emit() {
					return false
				}
			}
			start, end, multi = pos, le, false
		} else if start < 0 || end == start {
			start, end, multi = pos, le, false
		} else {
			end = le
			multi = true
		}
		pos = next
	}

	if start >= 0 && end > start {
		return emit()
	}
	return true
}

// entryStartAtOrAfter returns the offset of the first entry start in [pos, limit),
// or limit when there is none.
func entryStartAtOrAfter(data []byte, pos, limit int, isNewEntry func([]byte) bool) int {
	if pos <= 0 {
		return 0
	}
	if pos >= limit {
		return limit
	}
	if data[pos-1] != '\n' {
		nl := bytes.IndexByte(data[pos:limit], '\n')
		if nl == -1 {
			return limit
		}
		pos += nl + 1
	}
	for pos < limit {
		lineEnd := limit
		next := limit
		if nl := bytes.IndexByte(data[pos:limit], '\n'); nl != -1 {
			lineEnd = pos + nl
			next = lineEnd + 1
		}
		le := lineEnd
		if le > pos && data[le-1] == '\r' {
			le--
		}
		if isNewEntry(data[pos:le]) {
			return pos
		}
		pos = next
	}
	return limit
}

// firstLine returns the first physical line of data starting at pos (without "\r\n").
func firstLine(data []byte, pos int) []byte {
	end := len(data)
	if nl := bytes.IndexByte(data[pos:], '\n'); nl != -1 {
		end = pos + nl
	}
	if end > pos && data[end-1] == '\r' {
		end--
	}
	return data[pos:end]
}

// findChunkBoundaries splits data[lo:hi] into numChunks chunks aligned at entry starts.
func findChunkBoundaries(data []byte, lo, hi, numChunks int, isNewEntry func([]byte) bool) []int {
	if numChunks < 1 {
		numChunks = 1
	}
	boundaries := make([]int, 0, numChunks+1)
	boundaries = append(boundaries, lo)
	chunkSize := (hi - lo) / numChunks
	for i := 1; i < numChunks && chunkSize > 0; i++ {
		b := entryStartAtOrAfter(data, lo+i*chunkSize, hi, isNewEntry)
		if b > boundaries[len(boundaries)-1] && b < hi {
			boundaries = append(boundaries, b)
		}
	}
	return append(boundaries, hi)
}

// countLines counts '\n' in data using all CPUs for large inputs.
func countLines(data []byte) int {
	const minParallel = 32 * 1024 * 1024
	n := runtime.NumCPU()
	if len(data) < minParallel || n < 2 {
		return bytes.Count(data, []byte{'\n'})
	}
	part := len(data) / n
	counts := make([]int, n)
	var wg sync.WaitGroup
	for i := 0; i < n; i++ {
		s := i * part
		e := s + part
		if i == n-1 {
			e = len(data)
		}
		wg.Add(1)
		go func(i, s, e int) {
			defer wg.Done()
			defer guardMmap()()
			counts[i] = bytes.Count(data[s:e], []byte{'\n'})
		}(i, s, e)
	}
	wg.Wait()
	total := 0
	for _, c := range counts {
		total += c
	}
	return total
}

// totalLineCount returns the number of lines like `wc -l` plus a final unterminated line.
func totalLineCount(data []byte) int {
	if len(data) == 0 {
		return 0
	}
	n := countLines(data)
	if data[len(data)-1] != '\n' {
		n++
	}
	return n
}

// lineStartOffset returns the byte offset where line number n (0-based) begins,
// or len(data) if the data has fewer lines.
func lineStartOffset(data []byte, n int) int {
	if n <= 0 {
		return 0
	}
	const block = 1 << 20
	pos := 0
	for pos < len(data) {
		end := pos + block
		if end > len(data) {
			end = len(data)
		}
		c := bytes.Count(data[pos:end], []byte{'\n'})
		if c < n {
			n -= c
			pos = end
			continue
		}
		for n > 0 {
			nl := bytes.IndexByte(data[pos:end], '\n')
			pos += nl + 1
			n--
		}
		return pos
	}
	return len(data)
}

// lineReader reads physical lines from a stream without failing on very long lines:
// anything above maxLen bytes is dropped up to the next newline.
type lineReader struct {
	r      *bufio.Reader
	maxLen int
	buf    []byte
}

func newLineReader(r io.Reader, maxLen int) *lineReader {
	return &lineReader{r: bufio.NewReaderSize(r, 256*1024), maxLen: maxLen}
}

// next returns the next line without its "\r\n". The slice is valid until the next call.
func (lr *lineReader) next() ([]byte, error) {
	lr.buf = lr.buf[:0]
	truncated := false
	for {
		chunk, err := lr.r.ReadSlice('\n')
		if !truncated {
			room := lr.maxLen - len(lr.buf)
			if len(chunk) > room {
				lr.buf = append(lr.buf, chunk[:room]...)
				truncated = true
			} else {
				lr.buf = append(lr.buf, chunk...)
			}
		}
		if errors.Is(err, bufio.ErrBufferFull) {
			continue
		}
		if err != nil {
			if len(lr.buf) == 0 {
				return nil, err
			}
			return trimEOL(lr.buf), nil
		}
		return trimEOL(lr.buf), nil
	}
}

func trimEOL(b []byte) []byte {
	if len(b) > 0 && b[len(b)-1] == '\n' {
		b = b[:len(b)-1]
	}
	if len(b) > 0 && b[len(b)-1] == '\r' {
		b = b[:len(b)-1]
	}
	return b
}

// streamEntries joins lines from r into entries (amortised O(n) for multi-line entries)
// and calls fn with a freshly allocated entry and its 0-based start line.
func streamEntries(r io.Reader, isNewEntry func([]byte) bool, fn func(entry []byte, startLine int) bool) error {
	lr := newLineReader(r, maxStreamLine)
	var pending []byte
	pendingLine := 0
	lineNo := 0

	for {
		line, err := lr.next()
		if err != nil {
			if err == io.EOF {
				break
			}
			return err
		}

		if isNewEntry(line) {
			if len(pending) > 0 {
				if !fn(pending, pendingLine) {
					return nil
				}
			}
			pending = append(make([]byte, 0, len(line)), line...)
			pendingLine = lineNo
		} else if len(pending) == 0 {
			pending = append(make([]byte, 0, len(line)), line...)
			pendingLine = lineNo
		} else if len(pending) < maxEntrySize {
			pending = append(pending, '\n')
			pending = append(pending, line...)
		}
		lineNo++
	}

	if len(pending) > 0 {
		fn(pending, pendingLine)
	}
	return nil
}
