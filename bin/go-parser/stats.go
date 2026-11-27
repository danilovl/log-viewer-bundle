package main

import (
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"sync"
	"time"
)

const (
	batchSize = 2000
	chanSize  = 256
	// Files smaller than this are processed by a single worker.
	minParallelSize = 4 * 1024 * 1024
)

type Stats struct {
	UpdatedAt string         `json:"updated_at"`
	Size      int64          `json:"size"`
	Total     int            `json:"total"`
	Levels    map[string]int `json:"levels"`
	Channels  map[string]int `json:"channels"`
	Timeline  map[string]int `json:"timeline"`
}

func newStats(size int64, updatedAt string) *Stats {
	return &Stats{
		UpdatedAt: updatedAt,
		Size:      size,
		Levels:    make(map[string]int),
		Channels:  make(map[string]int),
		Timeline:  make(map[string]int),
	}
}

// localStats is a per-worker accumulator (no locking, no per-entry allocation).
type localStats struct {
	total    int
	levels   map[string]int
	channels map[string]int
	timeline map[int64]int
}

// Level/channel keys may point into mmap memory or entry buffers; they are
// copied once per distinct key in mergeInto.
func newLocalStats(withMaps bool) *localStats {
	ls := &localStats{}
	if withMaps {
		ls.levels = make(map[string]int, 8)
		ls.channels = make(map[string]int, 16)
		ls.timeline = make(map[int64]int, 256)
	}
	return ls
}

func (ls *localStats) add(entry *LogEntry) {
	ls.total++
	if ls.levels == nil {
		return
	}
	ls.levels[entry.Level]++
	ls.channels[entry.Channel]++
	if entry.Time.Year() > 1 {
		ls.timeline[hourKey(entry.Time)]++
	}
}

func (ls *localStats) mergeInto(stats *Stats, mu *sync.Mutex) {
	mu.Lock()
	defer mu.Unlock()
	stats.Total += ls.total
	for k, v := range ls.levels {
		stats.Levels[cloneString(k)] += v
	}
	for k, v := range ls.channels {
		stats.Channels[cloneString(k)] += v
	}
	for k, v := range ls.timeline {
		stats.Timeline[hourKeyString(k)] += v
	}
}

func cloneString(s string) string {
	return string([]byte(s))
}

// aggregate runs the filter over the whole file using all CPUs and feeds matches into
// per-worker accumulators. withMaps=false only counts.
func aggregate(cfg RemoteConfig, parser LogParser, f *entryFilter, withMaps bool) (*Stats, error) {
	file, size, err := OpenLogFile(cfg)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	updatedAt := time.Now().Format(time.RFC3339)
	if osF, ok := file.(*os.File); ok {
		if fi, err := osF.Stat(); err == nil {
			updatedAt = fi.ModTime().Format(time.RFC3339)
		}
	}

	stats := newStats(size, updatedAt)
	var mu sync.Mutex
	numWorkers := runtime.NumCPU()
	isNewEntry := parser.IsNewEntry
	fileName := stripGzExt(filepath.Base(cfg.FilePath))

	var mmapData []byte
	if cfg.Host == "" && !isGzipFile(cfg.FilePath) {
		if osF, ok := file.(*os.File); ok {
			if data, mErr := Mmap(osF); mErr == nil && len(data) > 0 {
				mmapData = data
				defer Munmap(mmapData)
			}
		}
	}

	if mmapData != nil {
		defer guardMmap()()
		MadviseSequential(mmapData)
		lo, hi := searchRange(mmapData, parser, f, time.Time{}, true)
		if hi-lo < minParallelSize {
			numWorkers = 1
		}
		boundaries := findChunkBoundaries(mmapData, lo, hi, numWorkers, isNewEntry)

		var wg sync.WaitGroup
		for ci := 0; ci+1 < len(boundaries); ci++ {
			start, end := boundaries[ci], boundaries[ci+1]
			if start >= end {
				continue
			}
			wg.Add(1)
			go func(chunk []byte) {
				defer wg.Done()
				defer guardMmap()()
				ls := newLocalStats(withMaps)
				scanEntries(chunk, isNewEntry, func(line []byte, _ int) bool {
					if entry := f.match(line, fileName); entry != nil {
						ls.add(entry)
						PutEntry(entry)
					}
					return true
				})
				ls.mergeInto(stats, &mu)
			}(mmapData[start:end])
		}
		wg.Wait()
		return stats, nil
	}

	// Streaming path: gzip, remote or non-mmapable files. Memory stays O(batch).
	var reader io.Reader = file
	if cfg.Host == "" && isGzipFile(cfg.FilePath) {
		gr, gErr := newGzipReader(file)
		if gErr != nil {
			return nil, gErr
		}
		defer gr.Close()
		reader = gr
	}

	jobs := make(chan [][]byte, chanSize)
	pool := sync.Pool{New: func() interface{} { return make([][]byte, 0, batchSize) }}
	var wg sync.WaitGroup
	for i := 0; i < numWorkers; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			ls := newLocalStats(withMaps)
			for batch := range jobs {
				for _, line := range batch {
					if entry := f.match(line, fileName); entry != nil {
						ls.add(entry)
						PutEntry(entry)
					}
				}
				pool.Put(batch[:0])
			}
			ls.mergeInto(stats, &mu)
		}()
	}

	batch := pool.Get().([][]byte)[:0]
	streamErr := streamEntries(reader, isNewEntry, func(entry []byte, _ int) bool {
		batch = append(batch, entry)
		if len(batch) >= batchSize {
			jobs <- batch
			batch = pool.Get().([][]byte)[:0]
		}
		return true
	})
	if len(batch) > 0 {
		jobs <- batch
	}
	close(jobs)
	wg.Wait()

	if streamErr != nil {
		fmt.Fprintf(os.Stderr, "Error reading file: %v\n", streamErr)
	}
	return stats, nil
}

// runStatsDashboard collects full-file stats (only date filters).
func runStatsDashboard(cfg RemoteConfig, parser LogParser, dateFrom, dateTo string) {
	f, err := newEntryFilter(parser, "", "", "", "", false, false, dateFrom, dateTo)
	if err != nil {
		fail("%v", err)
	}
	stats, err := aggregate(cfg, parser, f, true)
	if err != nil {
		fail("Error opening file: %v", err)
	}
	writeJSON(stats)
}

// runStatsFilter collects stats with filter support.
func runStatsFilter(cfg RemoteConfig, parser LogParser, filterLevel, filterLevels, filterChannel, filterSearch string, searchRegex, searchCaseSensitive bool, dateFrom, dateTo string) {
	f, err := newEntryFilter(parser, filterLevel, filterLevels, filterChannel, filterSearch, searchRegex, searchCaseSensitive, dateFrom, dateTo)
	if err != nil {
		fail("%v", err)
	}
	stats, err := aggregate(cfg, parser, f, true)
	if err != nil {
		fail("Error opening file: %v", err)
	}
	writeJSON(stats)
}

// runCount counts entries with filter support.
func runCount(cfg RemoteConfig, parser LogParser, filterLevel, filterLevels, filterChannel, filterSearch string, searchRegex, searchCaseSensitive bool, dateFrom, dateTo string) {
	f, err := newEntryFilter(parser, filterLevel, filterLevels, filterChannel, filterSearch, searchRegex, searchCaseSensitive, dateFrom, dateTo)
	if err != nil {
		fail("%v", err)
	}
	stats, err := aggregate(cfg, parser, f, false)
	if err != nil {
		fail("Error opening file: %v", err)
	}
	fmt.Printf("{\"total\":%d}\n", stats.Total)
}

func writeJSON(v interface{}) {
	resData, _ := json.Marshal(v)
	fmt.Println(string(resData))
}

func fail(format string, args ...interface{}) {
	fmt.Fprintf(os.Stderr, format+"\n", args...)
	os.Exit(1)
}
