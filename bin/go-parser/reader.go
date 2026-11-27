package main

import (
	"bytes"
	"encoding/json"
	"io"
	"os"
)

func runFileContent(cfg RemoteConfig, limit, offset int) {
	if limit <= 0 {
		limit = 50
	}
	if offset < 0 {
		offset = 0
	}

	f, _, err := OpenLogFile(cfg)
	if err != nil {
		fail("Error opening file: %v", err)
	}
	defer f.Close()

	lines := []string{}
	totalLines := 0

	var mmapData []byte
	if cfg.Host == "" && !isGzipFile(cfg.FilePath) {
		if osF, ok := f.(*os.File); ok {
			if data, mErr := Mmap(osF); mErr == nil && len(data) > 0 {
				mmapData = data
				defer Munmap(mmapData)
			}
		}
	}

	if mmapData != nil {
		defer guardMmap()()
		// Single pass: parallel line count + direct jump to the requested page.
		totalLines = totalLineCount(mmapData)
		pos := lineStartOffset(mmapData, offset)
		for len(lines) < limit && pos < len(mmapData) {
			end := len(mmapData)
			next := len(mmapData)
			if nl := bytes.IndexByte(mmapData[pos:], '\n'); nl != -1 {
				end = pos + nl
				next = end + 1
			}
			lines = append(lines, string(trimEOL(capLine(mmapData[pos:end]))))
			pos = next
		}
	} else {
		var reader io.Reader = f
		if cfg.Host == "" && isGzipFile(cfg.FilePath) {
			gr, gErr := newGzipReader(f)
			if gErr != nil {
				fail("Error reading gzip file: %v", gErr)
			}
			defer gr.Close()
			reader = gr
		}

		lr := newLineReader(reader, maxStreamLine)
		for {
			line, rErr := lr.next()
			if rErr != nil {
				if rErr != io.EOF {
					fail("Error reading file: %v", rErr)
				}
				break
			}
			if totalLines >= offset && len(lines) < limit {
				lines = append(lines, string(line))
			}
			totalLines++
		}
	}

	result := struct {
		Lines      []string `json:"lines"`
		Page       int      `json:"page"`
		Limit      int      `json:"limit"`
		TotalLines int      `json:"totalLines"`
	}{
		Lines:      lines,
		Page:       (offset / limit) + 1,
		Limit:      limit,
		TotalLines: totalLines,
	}

	json.NewEncoder(os.Stdout).Encode(result)
}

func capLine(line []byte) []byte {
	if len(line) > maxStreamLine {
		return line[:maxStreamLine]
	}
	return line
}
