package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

// binarySearchMmap finds the byte offset where entries with timestamp >= target begin.
func binarySearchMmap(data []byte, target time.Time, isNewEntry func([]byte) bool) int {
	lo, hi := 0, len(data)

	for hi-lo > 65536 {
		mid := lo + (hi-lo)/2
		entryPos := findEntryAfter(data, mid, isNewEntry)
		if entryPos < 0 || entryPos >= hi {
			hi = mid
			continue
		}

		ts := quickTimestamp(data, entryPos)
		t := parseTimestamp(ts)
		if t.IsZero() {
			hi = mid
			continue
		}

		if t.Before(target) {
			lo = entryPos + 1
		} else {
			hi = entryPos
		}
	}

	// Align to entry boundary within remaining range
	for lo < len(data) {
		lineEnd := bytes.IndexByte(data[lo:], '\n')
		var line []byte
		if lineEnd == -1 {
			line = data[lo:]
		} else {
			line = data[lo : lo+lineEnd]
		}
		if (lo == 0 || data[lo-1] == '\n') && isNewEntry(line) {
			return lo
		}
		if lineEnd == -1 {
			break
		}
		lo += lineEnd + 1
	}
	return len(data)
}

// findEntryAfter returns the byte position of the first log entry start at or after pos.
func findEntryAfter(data []byte, pos int, isNewEntry func([]byte) bool) int {
	for i := pos; i < len(data); i++ {
		if i == 0 || data[i-1] == '\n' {
			lineEnd := bytes.IndexByte(data[i:], '\n')
			var line []byte
			if lineEnd == -1 {
				line = data[i:]
			} else {
				line = data[i : i+lineEnd]
			}
			if isNewEntry(line) {
				return i
			}
		}
	}
	return -1
}

// quickTimestamp extracts the raw timestamp string from a line starting at pos.
func quickTimestamp(data []byte, pos int) string {
	if pos >= len(data) {
		return ""
	}

	if data[pos] == '[' {
		maxEnd := pos + 50
		if maxEnd > len(data) {
			maxEnd = len(data)
		}
		for i := pos + 1; i < maxEnd; i++ {
			if data[i] == ']' {
				return unsafeString(data[pos+1 : i])
			}
		}
	}
	// Try nginx error style: YYYY/MM/DD HH:MM:SS
	if pos+19 <= len(data) && data[pos+4] == '/' && data[pos+7] == '/' && data[pos+10] == ' ' {
		return unsafeString(data[pos : pos+19])
	}
	// Try access log style: IP - - [DD/Mon/YYYY:HH:MM:SS +ZZZZ]
	// Find '[' within the first 60 bytes of the line
	maxSearch := pos + 60
	if maxSearch > len(data) {
		maxSearch = len(data)
	}
	for i := pos; i < maxSearch; i++ {
		if data[i] == '[' {
			maxEnd := i + 50
			if maxEnd > len(data) {
				maxEnd = len(data)
			}
			for j := i + 1; j < maxEnd; j++ {
				if data[j] == ']' {
					ts := data[i+1 : j]
					if len(ts) >= 20 && ts[2] == '/' && ts[6] == '/' && ts[11] == ':' {
						return unsafeString(ts)
					}
					break
				}
			}
			break
		}
		if data[i] == '\n' {
			break
		}
	}
	// Try syslog: Mon DD HH:MM:SS
	if pos+15 <= len(data) && data[pos+3] == ' ' && data[pos+6] == ' ' && data[pos+9] == ':' {
		return unsafeString(data[pos : pos+15])
	}
	return ""
}

func emitMmapLinesAsc(data []byte, isNewEntry func([]byte) bool, emit func([]byte) bool) {
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
				if !emit(pending) {
					return
				}
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
		emit(pending)
	}
}

func emitMmapLinesDesc(data []byte, stop <-chan struct{}, isNewEntry func([]byte) bool, emit func([]byte) bool) bool {
	end := len(data)
	var pending []byte

	for i := len(data) - 1; i >= 0; i-- {
		if data[i] != '\n' {
			continue
		}

		if stop != nil {
			select {
			case <-stop:
				return false
			default:
			}
		}

		line := data[i+1 : end]
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}

		if isNewEntry(line) {
			var fullLine []byte
			if len(pending) > 0 {
				fullLine = make([]byte, len(line)+1+len(pending))
				copy(fullLine, line)
				fullLine[len(line)] = '\n'
				copy(fullLine[len(line)+1:], pending)
				pending = nil
			} else {
				fullLine = line
			}
			if !emit(fullLine) {
				return false
			}
		} else {
			if len(pending) == 0 {
				pending = make([]byte, len(line))
				copy(pending, line)
			} else if len(pending) < 10*1024*1024 {
				newPending := make([]byte, len(line)+1+len(pending))
				copy(newPending, line)
				newPending[len(line)] = '\n'
				copy(newPending[len(line)+1:], pending)
				pending = newPending
			}
		}

		end = i
	}

	if end > 0 {
		if stop != nil {
			select {
			case <-stop:
				return false
			default:
			}
		}

		line := data[:end]
		if len(line) > 0 && line[len(line)-1] == '\r' {
			line = line[:len(line)-1]
		}

		if isNewEntry(line) {
			var fullLine []byte
			if len(pending) > 0 {
				fullLine = make([]byte, len(line)+1+len(pending))
				copy(fullLine, line)
				fullLine[len(line)] = '\n'
				copy(fullLine[len(line)+1:], pending)
				pending = nil
			} else {
				fullLine = line
			}
			if !emit(fullLine) {
				return false
			}
		} else if len(line) > 0 {
			if len(pending) > 0 {
				fullLine := make([]byte, len(line)+1+len(pending))
				copy(fullLine, line)
				fullLine[len(line)] = '\n'
				copy(fullLine[len(line)+1:], pending)
				emit(fullLine)
			} else {
				emit(line)
			}
		}
	} else if len(pending) > 0 {
		emit(pending)
	}

	return true
}

func runLogs(cfg RemoteConfig, parser LogParser, limit, offset int, cursor, level, levels, channel, search string, searchRegex, searchCaseSensitive bool, dateFrom, dateTo, sort string) {
	var cursorTime time.Time
	if cursor != "" {
		cursorTime, _ = time.Parse(time.RFC3339Nano, cursor)
		if cursorTime.IsZero() {
			cursorTime, _ = time.Parse("2006-01-02T15:04:05.000000Z07:00", cursor)
		}
	}

	var dFrom, dTo time.Time
	if dateFrom != "" {
		dFrom = parseTimestamp(dateFrom)
	}
	if dateTo != "" {
		dTo = parseTimestamp(dateTo)
	}

	var searchRe *regexp.Regexp
	if searchRegex && search != "" {
		pStr := search
		if !searchCaseSensitive && !strings.HasPrefix(pStr, "(?i)") {
			pStr = "(?i)" + pStr
		}
		searchRe, _ = regexp.Compile(pStr)
	}

	searchVal := search
	searchASCII := true
	if search != "" {
		if !searchCaseSensitive && !searchRegex {
			searchVal = strings.ToLower(search)
		}
		searchASCII = isASCIIString(searchVal)
	}
	needTime := !cursorTime.IsZero()

	fileName := stripGzExt(filepath.Base(cfg.FilePath))
	isNewEntry := parser.IsNewEntry

	levelUpper := strings.ToUpper(level)
	var levelsUpper map[string]struct{}
	if levels != "" {
		levelsUpper = make(map[string]struct{})
		for _, s := range strings.Split(levels, ",") {
			levelsUpper[strings.ToUpper(strings.TrimSpace(s))] = struct{}{}
		}
	}
	var bLevelLower, bChannelLower []byte
	levelASCII := isASCIIString(level)
	channelASCII := isASCIIString(channel)
	if level != "" {
		bLevelLower = []byte(strings.ToLower(level))
	}
	if channel != "" {
		bChannelLower = []byte(strings.ToLower(channel))
	}
	if searchVal != "" && !searchRegex && !searchCaseSensitive {
		// no bSearchLower
	}

	var mmapData []byte
	totalLines := 0

	if cfg.Host == "" && isGzipFile(cfg.FilePath) {
		data, err := readGzipFull(cfg.FilePath)
		if err != nil {
			fmt.Fprintf(os.Stderr, "Error reading gzip file: %v\n", err)
			os.Exit(1)
		}
		mmapData = data
		totalLines = bytes.Count(mmapData, []byte("\n"))
		if len(mmapData) > 0 && mmapData[len(mmapData)-1] != '\n' {
			totalLines++
		}
	} else {
		f, _, err := OpenLogFile(cfg)
		if err != nil {
			fmt.Fprintf(os.Stderr, "Error opening file: %v\n", err)
			os.Exit(1)
		}
		defer f.Close()

		if cfg.Host == "" {
			if osF, ok := f.(*os.File); ok {
				if data, mErr := Mmap(osF); mErr == nil && len(data) > 0 {
					mmapData = data
					totalLines = bytes.Count(mmapData, []byte("\n"))
					if len(mmapData) > 0 && mmapData[len(mmapData)-1] != '\n' {
						totalLines++
					}
					if sort == "asc" {
						MadviseSequential(mmapData)
					}
				}
			}
		}
	}

	w := bufio.NewWriterSize(os.Stdout, 64*1024)
	defer w.Flush()
	enc := json.NewEncoder(w)

	collected := 0
	skipped := 0
	currentLineNumber := 0
	if sort == "desc" {
		currentLineNumber = totalLines - 1
	}

	processLine := func(line []byte) bool {
		numLines := bytes.Count(line, []byte("\n")) + 1
		defer func() {
			if sort == "desc" {
				currentLineNumber -= numLines
			} else {
				currentLineNumber += numLines
			}
		}()

		if len(line) == 0 {
			return true
		}
		
		isAllSpaces := true
		for _, b := range line {
			if b != ' ' && b != '\t' && b != '\r' && b != '\n' {
				isAllSpaces = false
				break
			}
		}
		if isAllSpaces {
			return true
		}

		_ = needTime

		// Byte-level pre-filters for fast rejection before any parsing
		if level != "" && levelASCII && !containsFoldASCIIBytes(line, bLevelLower) {
			return true
		}
		if channel != "" && channelASCII && !containsFoldASCIIBytes(line, bChannelLower) {
			return true
		}

		if searchVal != "" {
			if !containsSearchValueBytes(line, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe) {
				return true
			}
		}

		entry := parser.Parse(line, fileName, true, true)
		if entry == nil {
			return true
		}
		if sort == "desc" {
			entry.LineNumber = currentLineNumber - (numLines - 1)
		} else {
			entry.LineNumber = currentLineNumber
		}
		
		if !parser.IsNewEntry(line) && entry.Timestamp == "" {
			return true
		}

		entryTime := entry.Time
		eTimeStr := ""
		if entryTime.Year() > 1 {
			eTimeStr = entryTime.Format("2006-01-02 15:04:05")
		} else if entry.Timestamp != "" {
			// If we have a timestamp but couldn't parse it into time.Time, 
			// it's likely a format we don't support or partially support.
			// But for consistency with PHP, we should probably try harder.
			// However, if we can't parse it, we don't filter it.
		}

		if !dFrom.IsZero() && eTimeStr != "" {
			dFromStr := dFrom.Format("2006-01-02 15:04:05")
			if eTimeStr < dFromStr {
				PutEntry(entry)
				return true
			}
		}
		if !dTo.IsZero() && eTimeStr != "" {
			dToStr := dTo.Format("2006-01-02 15:04:05")
			if eTimeStr > dToStr {
				PutEntry(entry)
				return true
			}
		}


		if levelUpper != "" && entry.Level != levelUpper {
			PutEntry(entry)
			return true
		}
		if levelsUpper != nil {
			if _, ok := levelsUpper[entry.Level]; !ok {
				PutEntry(entry)
				return true
			}
		}
		if channel != "" && !strings.EqualFold(entry.Channel, channel) {
			PutEntry(entry)
			return true
		}
		if searchVal != "" {
			if !containsSearchValue(entry.Message, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe) &&
				(entry.SQL == "" || !containsSearchValue(entry.SQL, searchVal, searchRegex, searchCaseSensitive, searchASCII, searchRe)) {
				PutEntry(entry)
				return true
			}
		}

		if !cursorTime.IsZero() {
			if sort == "asc" {
				if !entry.Time.After(cursorTime) {
					PutEntry(entry)
					return true
				}
			} else {
				if !entry.Time.Before(cursorTime) {
					PutEntry(entry)
					return true
				}
			}
		}

		if skipped < offset {
			skipped++
			PutEntry(entry)
			return true
		}

		enc.Encode(entry)
		PutEntry(entry)

		collected++
		return collected < limit
	}

	if mmapData != nil {
		scanData := mmapData
		if !cursorTime.IsZero() {
			cursorOffset := binarySearchMmap(mmapData, cursorTime, isNewEntry)
			if sort == "asc" {
				if cursorOffset > 0 {
					scanData = mmapData[cursorOffset:]
					currentLineNumber = bytes.Count(mmapData[:cursorOffset], []byte("\n"))
				}
			} else {
				if cursorOffset < len(mmapData) {
					scanData = mmapData[:cursorOffset]
					currentLineNumber = bytes.Count(mmapData[:cursorOffset], []byte("\n"))
				}
			}
		} else if !dFrom.IsZero() && sort == "asc" {
			cursorOffset := binarySearchMmap(mmapData, dFrom, isNewEntry)
			if cursorOffset > 0 {
				scanData = mmapData[cursorOffset:]
				currentLineNumber = bytes.Count(mmapData[:cursorOffset], []byte("\n"))
			}
		}

		if sort == "asc" {
			emitMmapLinesAsc(scanData, isNewEntry, processLine)
		} else {
			emitMmapLinesDesc(scanData, nil, isNewEntry, processLine)
		}
		return
	}

	// Fallback: file-based line reading (no mmap)
	var pending []byte
	stopped := false
	callback := func(line []byte) bool {
		if isNewEntry(line) {
			if len(pending) > 0 {
				if !processLine(pending) {
					stopped = true
					return false
				}
			}
			pending = make([]byte, len(line))
			copy(pending, line)
		} else {
			if len(pending) == 0 {
				pending = make([]byte, len(line))
				copy(pending, line)
			} else if len(pending) < 10*1024*1024 {
				if sort == "asc" {
					newPending := make([]byte, len(pending)+1+len(line))
					copy(newPending, pending)
					newPending[len(pending)] = '\n'
					copy(newPending[len(pending)+1:], line)
					pending = newPending
				} else {
					newPending := make([]byte, len(line)+1+len(pending))
					copy(newPending, line)
					newPending[len(line)] = '\n'
					copy(newPending[len(line)+1:], pending)
					pending = newPending
				}
			}
		}
		return true
	}

	if sort == "asc" {
		_ = forwardLineReader(cfg, callback)
	} else {
		_ = reverseLineReader(cfg, callback)
	}
	if len(pending) > 0 && !stopped {
		processLine(pending)
	}
}
