package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"strings"
	"sync"
	"time"
)

const (
	batchSize = 2000
	chanSize  = 1024
)

type Stats struct {
	UpdatedAt string         `json:"updated_at"`
	Size      int64          `json:"size"`
	Total     int            `json:"total"`
	Levels    map[string]int `json:"levels"`
	Channels  map[string]int `json:"channels"`
	Timeline  map[string]int `json:"timeline"`
}

var batchPool = sync.Pool{
	New: func() interface{} {
		return make([][]byte, 0, batchSize)
	},
}

func appendStatsEntry(currentBatch *[][]byte, jobs chan<- [][]byte, entry []byte) {
	if len(entry) == 0 {
		return
	}
	*currentBatch = append(*currentBatch, entry)
	if len(*currentBatch) >= batchSize {
		jobs <- *currentBatch
		*currentBatch = batchPool.Get().([][]byte)[:0]
	}
}

func processMmap(data []byte, isNewEntry func([]byte) bool, jobs chan<- [][]byte) {
	currentBatch := batchPool.Get().([][]byte)[:0]
	offset := 0
	var pending []byte

	for offset < len(data) {
		end := bytes.IndexByte(data[offset:], '\n')
		var line []byte
		if end == -1 {
			line = data[offset:]
			offset = len(data)
		} else {
			line = data[offset : offset+end]
			offset += end + 1
		}
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}

		if isNewEntry(line) {
			if len(pending) > 0 {
				appendStatsEntry(&currentBatch, jobs, pending)
			}
			pending = line
		} else {
			if len(pending) == 0 {
				pending = line
			} else if len(pending) < 10*1024*1024 {
				newPending := make([]byte, len(pending)+1+len(line))
				copy(newPending, pending)
				newPending[len(pending)] = '\n'
				copy(newPending[len(pending)+1:], line)
				pending = newPending
			}
		}
	}
	if len(pending) > 0 {
		appendStatsEntry(&currentBatch, jobs, pending)
	}
	if len(currentBatch) > 0 {
		jobs <- currentBatch
	} else {
		batchPool.Put(currentBatch)
	}
}

func processScanner(f io.Reader, isNewEntry func([]byte) bool, jobs chan<- [][]byte) {
	scanner := bufio.NewScanner(f)
	buf := make([]byte, 64*1024)
	scanner.Buffer(buf, 1024*1024)

	currentBatch := batchPool.Get().([][]byte)[:0]
	var pending []byte

	for scanner.Scan() {
		line := scanner.Bytes()
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}

		if isNewEntry(line) {
			if len(pending) > 0 {
				appendStatsEntry(&currentBatch, jobs, pending)
			}
			pending = make([]byte, len(line))
			copy(pending, line)
		} else {
			if len(pending) == 0 {
				pending = make([]byte, len(line))
				copy(pending, line)
			} else if len(pending) < 10*1024*1024 {
				newPending := make([]byte, len(pending)+1+len(line))
				copy(newPending, pending)
				newPending[len(pending)] = '\n'
				copy(newPending[len(pending)+1:], line)
				pending = newPending
			}
		}
	}
	if len(pending) > 0 {
		appendStatsEntry(&currentBatch, jobs, pending)
	}
	if len(currentBatch) > 0 {
		jobs <- currentBatch
	} else {
		batchPool.Put(currentBatch)
	}
	if err := scanner.Err(); err != nil {
		fmt.Fprintf(os.Stderr, "Error reading file: %v\n", err)
	}
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

func mergeLocalStats(stats *Stats, mu *sync.Mutex, lTotal int, lLevels, lChannels, lTimeline map[string]int) {
	mu.Lock()
	stats.Total += lTotal
	for k, v := range lLevels {
		stats.Levels[k] += v
	}
	for k, v := range lChannels {
		stats.Channels[k] += v
	}
	for k, v := range lTimeline {
		stats.Timeline[k] += v
	}
	mu.Unlock()
}

// findChunkBoundaries splits mmap data into numChunks chunks aligned at entry boundaries.
func findChunkBoundaries(data []byte, numChunks int, isNewEntry func([]byte) bool) []int {
	boundaries := make([]int, numChunks+1)
	boundaries[0] = 0
	boundaries[numChunks] = len(data)

	chunkSize := len(data) / numChunks
	for i := 1; i < numChunks; i++ {
		pos := i * chunkSize
		for pos < len(data)-1 {
			if data[pos] == '\n' {
				candidate := data[pos+1:]

				lineEnd := bytes.IndexByte(candidate, '\n')
				var line []byte
				if lineEnd == -1 {
					line = candidate
				} else {
					line = candidate[:lineEnd]
				}
				if isNewEntry(line) {
					pos++
					break
				}
			}
			pos++
		}
		boundaries[i] = pos
	}
	return boundaries
}

// scanChunkEntries iterates entries in a mmap chunk, calling fn for each complete log entry.
func scanChunkEntries(chunk []byte, isNewEntry func([]byte) bool, fn func([]byte)) {
	offset := 0
	var pending []byte

	for offset < len(chunk) {
		end := bytes.IndexByte(chunk[offset:], '\n')
		var line []byte
		if end == -1 {
			line = chunk[offset:]
			offset = len(chunk)
		} else {
			line = chunk[offset : offset+end]
			offset += end + 1
		}
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}

		if isNewEntry(line) {
			if len(pending) > 0 {
				fn(pending)
			}
			pending = line
		} else {
			if len(pending) == 0 {
				pending = line
			} else if len(pending) < 10*1024*1024 {
				newPending := make([]byte, len(pending)+1+len(line))
				copy(newPending, pending)
				newPending[len(pending)] = '\n'
				copy(newPending[len(pending)+1:], line)
				pending = newPending
			}
		}
	}
	if len(pending) > 0 {
		fn(pending)
	}
}

// runStatsDashboard collects full-file stats with no filters.
func runStatsDashboard(cfg RemoteConfig, parser LogParser) {
	file, size, err := OpenLogFile(cfg)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error opening file: %v\n", err)
		os.Exit(1)
	}
	defer file.Close()

	updatedAt := time.Now().Format(time.RFC3339)
	if f, ok := file.(*os.File); ok {
		if fi, err := f.Stat(); err == nil {
			updatedAt = fi.ModTime().Format(time.RFC3339)
		}
	}

	stats := newStats(size, updatedAt)
	var statsMu sync.Mutex
	numWorkers := runtime.NumCPU()
	fileName := stripGzExt(filepath.Base(cfg.FilePath))
	isNewEntry := parser.IsNewEntry

	var mmapData []byte
	var mmapErr error = fmt.Errorf("mmap not supported for remote")

	if cfg.Host == "" {
		if isGzipFile(cfg.FilePath) {
			mmapData, mmapErr = readGzipFull(cfg.FilePath)
		} else if f, ok := file.(*os.File); ok {
			mmapData, mmapErr = Mmap(f)
			if mmapErr == nil && len(mmapData) > 0 {
				defer Munmap(mmapData)
			}
		}
	}

	if mmapErr == nil && len(mmapData) > 0 {
		MadviseSequential(mmapData)

		boundaries := findChunkBoundaries(mmapData, numWorkers, isNewEntry)
		var wg sync.WaitGroup

		for ci := 0; ci < numWorkers; ci++ {
			start, end := boundaries[ci], boundaries[ci+1]
			if start >= end {
				continue
			}
			wg.Add(1)
			go func(chunk []byte) {
				defer wg.Done()
				lTotal := 0
				lLevels := make(map[string]int, 8)
				lChannels := make(map[string]int, 16)
				lTimeline := make(map[string]int, 64)

				scanChunkEntries(chunk, isNewEntry, func(line []byte) {
					if len(line) < 10 {
						return
					}
					level, channel, hour, ok := parser.ExtractStatsFields(line)
					if !ok {
						entry := parser.Parse(line, fileName, false, false)
						if entry == nil {
							return
						}
						lTotal++
						lLevels[entry.Level]++
						lChannels[entry.Channel]++
						if h := parseHourBucket(entry.Timestamp); h != "" {
							lTimeline[h]++
						}
						PutEntry(entry)
						return
					}
					lTotal++
					lLevels[level]++
					lChannels[channel]++
					if hour != "" {
						lTimeline[hour]++
					}
				})

				mergeLocalStats(stats, &statsMu, lTotal, lLevels, lChannels, lTimeline)
			}(mmapData[start:end])
		}
		wg.Wait()
	} else {
		jobs := make(chan [][]byte, chanSize)
		var wg sync.WaitGroup

		for i := 0; i < numWorkers; i++ {
			wg.Add(1)
			go func() {
				defer wg.Done()
				lTotal := 0
				lLevels := make(map[string]int, 8)
				lChannels := make(map[string]int, 16)
				lTimeline := make(map[string]int, 64)

				for batch := range jobs {
					for _, line := range batch {
						if len(line) < 10 {
							continue
						}
						level, channel, hour, ok := parser.ExtractStatsFields(line)
						if !ok {
							entry := parser.Parse(line, fileName, false, false)
							if entry == nil {
								continue
							}
							lTotal++
							lLevels[entry.Level]++
							lChannels[entry.Channel]++
							if h := parseHourBucket(entry.Timestamp); h != "" {
								lTimeline[h]++
							}
							PutEntry(entry)
							continue
						}
						lTotal++
						lLevels[level]++
						lChannels[channel]++
						if hour != "" {
							lTimeline[hour]++
						}
					}
					batchPool.Put(batch)
				}
				mergeLocalStats(stats, &statsMu, lTotal, lLevels, lChannels, lTimeline)
			}()
		}
		processScanner(file, isNewEntry, jobs)
		close(jobs)
		wg.Wait()
	}

	resData, _ := json.Marshal(stats)
	fmt.Println(string(resData))
}

// runStatsLog collects stats with filter support.
func runStatsLog(cfg RemoteConfig, parser LogParser, filterLevel, filterLevels, filterChannel, filterSearch string, searchRegex, searchCaseSensitive bool) {
	file, size, err := OpenLogFile(cfg)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error opening file: %v\n", err)
		os.Exit(1)
	}
	defer file.Close()

	updatedAt := time.Now().Format(time.RFC3339)
	if f, ok := file.(*os.File); ok {
		if fi, err := f.Stat(); err == nil {
			updatedAt = fi.ModTime().Format(time.RFC3339)
		}
	}

	stats := newStats(size, updatedAt)
	var statsMu sync.Mutex
	numWorkers := runtime.NumCPU()
	fileName := stripGzExt(filepath.Base(cfg.FilePath))
	isNewEntry := parser.IsNewEntry

	var searchRe *regexp.Regexp
	if searchRegex && filterSearch != "" {
		pStr := filterSearch
		if !searchCaseSensitive && !strings.HasPrefix(pStr, "(?i)") {
			pStr = "(?i)" + pStr
		}
		searchRe, _ = regexp.Compile(pStr)
	}

	searchVal := filterSearch
	searchASCII := true
	if filterSearch != "" {
		if !searchCaseSensitive && !searchRegex {
			searchVal = strings.ToLower(filterSearch)
		}
		searchASCII = isASCIIString(searchVal)
	}

	filterLevelUpper := strings.ToUpper(filterLevel)
	var filterLevelsUpper map[string]struct{}
	if filterLevels != "" {
		filterLevelsUpper = make(map[string]struct{})
		for _, s := range strings.Split(filterLevels, ",") {
			filterLevelsUpper[strings.ToUpper(strings.TrimSpace(s))] = struct{}{}
		}
	}

	levelASCII := isASCIIString(filterLevel)
	channelASCII := isASCIIString(filterChannel)
	var bLevelL, bChannelL, bSearchLower []byte
	if filterLevel != "" {
		bLevelL = []byte(strings.ToLower(filterLevel))
	}
	if filterChannel != "" {
		bChannelL = []byte(strings.ToLower(filterChannel))
	}
	if searchVal != "" && !searchRegex && !searchCaseSensitive {
		bSearchLower = []byte(searchVal)
	}

	// Shared filter logic for one entry line.
	processFilteredLine := func(line []byte, lTotal *int, lLevels, lChannels, lTimeline map[string]int) {
		if len(line) < 10 {
			return
		}

		if searchVal != "" && !searchRegex && !searchCaseSensitive {
			if searchASCII {
				if !containsFoldASCIIBytes(line, bSearchLower) {
					return
				}
			} else if !bytes.Contains(bytes.ToLower(line), bSearchLower) {
				return
			}
		}

		// Fast path: try parser's ExtractStatsFields first when no search filter
		if searchVal == "" {
			level, channel, hour, ok := parser.ExtractStatsFields(line)
			if ok {
				if filterLevelUpper != "" && level != filterLevelUpper {
					return
				}
				if filterLevelsUpper != nil {
					if _, ok := filterLevelsUpper[level]; !ok {
						return
					}
				}
				if filterChannel != "" && !strings.EqualFold(channel, filterChannel) {
					return
				}
				*lTotal++
				lLevels[level]++
				lChannels[channel]++
				if hour != "" {
					lTimeline[hour]++
				}
				return
			}
		}

		if filterLevel != "" && levelASCII {
			if !containsFoldASCIIBytes(line, bLevelL) {
				return
			}
		}
		if filterChannel != "" && channelASCII {
			if !containsFoldASCIIBytes(line, bChannelL) {
				return
			}
		}

		entry := parser.Parse(line, fileName, searchVal != "", false)
		if entry == nil {
			return
		}
		if filterLevelUpper != "" && entry.Level != filterLevelUpper {
			PutEntry(entry)
			return
		}
		if filterLevelsUpper != nil {
			if _, ok := filterLevelsUpper[entry.Level]; !ok {
				PutEntry(entry)
				return
			}
		}
		if filterChannel != "" && !strings.EqualFold(entry.Channel, filterChannel) {
			PutEntry(entry)
			return
		}
		if searchVal != "" {
			msgMatch := containsSearchValue(entry.Message, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe)
			sqlMatch := entry.SQL != "" && containsSearchValue(entry.SQL, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe)
			if !msgMatch && !sqlMatch {
				PutEntry(entry)
				return
			}
		}

		*lTotal++
		lLevels[entry.Level]++
		lChannels[entry.Channel]++
		if h := parseHourBucket(entry.Timestamp); h != "" {
			lTimeline[h]++
		}
		PutEntry(entry)
	}

	var mmapData []byte
	var mmapErr error = fmt.Errorf("mmap not supported for remote")

	if cfg.Host == "" {
		if isGzipFile(cfg.FilePath) {
			mmapData, mmapErr = readGzipFull(cfg.FilePath)
		} else if f, ok := file.(*os.File); ok {
			mmapData, mmapErr = Mmap(f)
			if mmapErr == nil && len(mmapData) > 0 {
				defer Munmap(mmapData)
			}
		}
	}

	if mmapErr == nil && len(mmapData) > 0 {
		MadviseSequential(mmapData)

		boundaries := findChunkBoundaries(mmapData, numWorkers, isNewEntry)
		var wg sync.WaitGroup

		for ci := 0; ci < numWorkers; ci++ {
			start, end := boundaries[ci], boundaries[ci+1]
			if start >= end {
				continue
			}
			wg.Add(1)
			go func(chunk []byte) {
				defer wg.Done()
				lTotal := 0
				lLevels := make(map[string]int, 8)
				lChannels := make(map[string]int, 16)
				lTimeline := make(map[string]int, 64)

				scanChunkEntries(chunk, isNewEntry, func(line []byte) {
					processFilteredLine(line, &lTotal, lLevels, lChannels, lTimeline)
				})

				mergeLocalStats(stats, &statsMu, lTotal, lLevels, lChannels, lTimeline)
			}(mmapData[start:end])
		}
		wg.Wait()
	} else {
		jobs := make(chan [][]byte, chanSize)
		var wg sync.WaitGroup

		for i := 0; i < numWorkers; i++ {
			wg.Add(1)
			go func() {
				defer wg.Done()
				lTotal := 0
				lLevels := make(map[string]int, 8)
				lChannels := make(map[string]int, 16)
				lTimeline := make(map[string]int, 64)

				for batch := range jobs {
					for _, line := range batch {
						processFilteredLine(line, &lTotal, lLevels, lChannels, lTimeline)
					}
					batchPool.Put(batch)
				}
				mergeLocalStats(stats, &statsMu, lTotal, lLevels, lChannels, lTimeline)
			}()
		}
		processScanner(file, isNewEntry, jobs)
		close(jobs)
		wg.Wait()
	}

	resData, _ := json.Marshal(stats)
	fmt.Println(string(resData))
}

// Backward-compatible aliases for old mode names.
func runStats(cfg RemoteConfig, parser LogParser) {
	runStatsDashboard(cfg, parser)
}

func runStatsFilter(cfg RemoteConfig, parser LogParser, filterLevel, filterLevels, filterChannel, filterSearch string, searchRegex, searchCaseSensitive bool) {
	runStatsLog(cfg, parser, filterLevel, filterLevels, filterChannel, filterSearch, searchRegex, searchCaseSensitive)
}

// runCount counts total entries with filter support.
func runCount(cfg RemoteConfig, parser LogParser, filterLevel, filterLevels, filterChannel, filterSearch string, searchRegex, searchCaseSensitive bool) {
	file, _, err := OpenLogFile(cfg)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error opening file: %v\n", err)
		os.Exit(1)
	}
	defer file.Close()

	numWorkers := runtime.NumCPU()
	fileName := stripGzExt(filepath.Base(cfg.FilePath))
	isNewEntry := parser.IsNewEntry

	var searchRe *regexp.Regexp
	if searchRegex && filterSearch != "" {
		pStr := filterSearch
		if !searchCaseSensitive && !strings.HasPrefix(pStr, "(?i)") {
			pStr = "(?i)" + pStr
		}
		searchRe, _ = regexp.Compile(pStr)
	}

	searchVal := filterSearch
	searchASCII := true
	if filterSearch != "" {
		if !searchCaseSensitive && !searchRegex {
			searchVal = strings.ToLower(filterSearch)
		}
		searchASCII = isASCIIString(searchVal)
	}

	filterLevelUpper := strings.ToUpper(filterLevel)
	var filterLevelsUpper map[string]struct{}
	if filterLevels != "" {
		filterLevelsUpper = make(map[string]struct{})
		for _, s := range strings.Split(filterLevels, ",") {
			filterLevelsUpper[strings.ToUpper(strings.TrimSpace(s))] = struct{}{}
		}
	}

	levelASCII := isASCIIString(filterLevel)
	channelASCII := isASCIIString(filterChannel)
	var bLevelL, bChannelL, bSearchLower []byte
	if filterLevel != "" {
		bLevelL = []byte(strings.ToLower(filterLevel))
	}
	if filterChannel != "" {
		bChannelL = []byte(strings.ToLower(filterChannel))
	}
	if searchVal != "" && !searchRegex && !searchCaseSensitive {
		bSearchLower = []byte(searchVal)
	}

	processCountLine := func(line []byte, lTotal *int) {
		if len(line) < 10 {
			return
		}

		if searchVal != "" && !searchRegex && !searchCaseSensitive {
			if searchASCII {
				if !containsFoldASCIIBytes(line, bSearchLower) {
					return
				}
			} else if !bytes.Contains(bytes.ToLower(line), bSearchLower) {
				return
			}
		}

		if searchVal == "" {
			level, channel, _, ok := parser.ExtractStatsFields(line)
			if ok {
				if filterLevelUpper != "" && level != filterLevelUpper {
					return
				}
				if filterLevelsUpper != nil {
					if _, ok := filterLevelsUpper[level]; !ok {
						return
					}
				}
				if filterChannel != "" && !strings.EqualFold(channel, filterChannel) {
					return
				}
				*lTotal++
				return
			}
		}

		if filterLevel != "" && levelASCII {
			if !containsFoldASCIIBytes(line, bLevelL) {
				return
			}
		}
		if filterChannel != "" && channelASCII {
			if !containsFoldASCIIBytes(line, bChannelL) {
				return
			}
		}

		entry := parser.Parse(line, fileName, searchVal != "", false)
		if entry == nil {
			return
		}
		if filterLevelUpper != "" && entry.Level != filterLevelUpper {
			PutEntry(entry)
			return
		}
		if filterLevelsUpper != nil {
			if _, ok := filterLevelsUpper[entry.Level]; !ok {
				PutEntry(entry)
				return
			}
		}
		if filterChannel != "" && !strings.EqualFold(entry.Channel, filterChannel) {
			PutEntry(entry)
			return
		}
		if searchVal != "" {
			msgMatch := containsSearchValue(entry.Message, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe)
			sqlMatch := entry.SQL != "" && containsSearchValue(entry.SQL, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe)
			if !msgMatch && !sqlMatch {
				PutEntry(entry)
				return
			}
		}

		*lTotal++
		PutEntry(entry)
	}

	total := 0
	var totalMu sync.Mutex

	var mmapData []byte
	var mmapErr error = fmt.Errorf("mmap not supported for remote")

	if cfg.Host == "" {
		if isGzipFile(cfg.FilePath) {
			mmapData, mmapErr = readGzipFull(cfg.FilePath)
		} else if f, ok := file.(*os.File); ok {
			mmapData, mmapErr = Mmap(f)
			if mmapErr == nil && len(mmapData) > 0 {
				defer Munmap(mmapData)
			}
		}
	}

	if mmapErr == nil && len(mmapData) > 0 {
		MadviseSequential(mmapData)
		boundaries := findChunkBoundaries(mmapData, numWorkers, isNewEntry)
		var wg sync.WaitGroup
		for ci := 0; ci < numWorkers; ci++ {
			start, end := boundaries[ci], boundaries[ci+1]
			if start >= end {
				continue
			}
			wg.Add(1)
			go func(chunk []byte) {
				defer wg.Done()
				lTotal := 0
				scanChunkEntries(chunk, isNewEntry, func(line []byte) {
					processCountLine(line, &lTotal)
				})
				totalMu.Lock()
				total += lTotal
				totalMu.Unlock()
			}(mmapData[start:end])
		}
		wg.Wait()
	} else {
		jobs := make(chan [][]byte, chanSize)
		var wg sync.WaitGroup
		for i := 0; i < numWorkers; i++ {
			wg.Add(1)
			go func() {
				defer wg.Done()
				lTotal := 0
				for batch := range jobs {
					for _, line := range batch {
						processCountLine(line, &lTotal)
					}
					batchPool.Put(batch)
				}
				totalMu.Lock()
				total += lTotal
				totalMu.Unlock()
			}()
		}
		processScanner(file, isNewEntry, jobs)
		close(jobs)
		wg.Wait()
	}

	fmt.Printf("{\"total\":%d}\n", total)
}
