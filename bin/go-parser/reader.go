package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"os"
)

func runFileContent(cfg RemoteConfig, limit, offset int) {
	var f io.ReadCloser
	var err error
	var totalLines int

	if cfg.Host == "" && isGzipFile(cfg.FilePath) {
		data, gErr := readGzipFull(cfg.FilePath)
		if gErr != nil {
			fmt.Fprintf(os.Stderr, "Error reading gzip file: %v\n", gErr)
			os.Exit(1)
		}
		totalLines = bytes.Count(data, []byte("\n"))
		if len(data) > 0 && data[len(data)-1] != '\n' {
			totalLines++
		}
		f = io.NopCloser(bytes.NewReader(data))
	} else {
		f, _, err = OpenLogFile(cfg)
		if err != nil {
			fmt.Fprintf(os.Stderr, "Error opening file: %v\n", err)
			os.Exit(1)
		}
		if cfg.Host == "" {
			if osF, ok := f.(*os.File); ok {
				if data, mErr := Mmap(osF); mErr == nil && len(data) > 0 {
					totalLines = bytes.Count(data, []byte("\n"))
					if len(data) > 0 && data[len(data)-1] != '\n' {
						totalLines++
					}
					Munmap(data)
				}
			}
		}
	}
	defer f.Close()

	if rs, ok := f.(io.ReadSeeker); ok {
		if _, err := rs.Seek(0, io.SeekStart); err != nil {
			// Seek failed
		}
	}

	scanner := bufio.NewScanner(f)
	buf := make([]byte, 64*1024)
	scanner.Buffer(buf, 10*1024*1024)

	lines := []string{}
	currentLine := 0
	count := 0

	for scanner.Scan() {
		if currentLine >= offset && count < limit {
			lines = append(lines, scanner.Text())
			count++
		}
		currentLine++
	}

	if err := scanner.Err(); err != nil {
		fmt.Fprintf(os.Stderr, "Scanner error: %v\n", err)
	}

	if totalLines == 0 {
		totalLines = currentLine
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

func forwardLineReader(cfg RemoteConfig, callback func([]byte) bool) error {
	f, _, err := OpenLogFile(cfg)
	if err != nil {
		return err
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	buf := make([]byte, 0, 64*1024)
	scanner.Buffer(buf, 10*1024*1024)

	for scanner.Scan() {
		line := scanner.Bytes()
		lineCopy := make([]byte, len(line))
		copy(lineCopy, line)
		if !callback(lineCopy) {
			return nil
		}
	}
	return scanner.Err()
}

func reverseLineReader(cfg RemoteConfig, callback func([]byte) bool) error {
	f, size, err := OpenLogFile(cfg)
	if err != nil {
		return err
	}
	defer f.Close()

	rs, ok := f.(io.ReadSeeker)
	if !ok || size == 0 {
		if !ok && cfg.Host != "" {
			return fmt.Errorf("reverse reading is not supported for remote host type: %s", cfg.Type)
		}
		return nil
	}

	const chunkSize = 65536
	buf := make([]byte, chunkSize)
	pos := size
	var leftover []byte

	for pos > 0 {
		readSize := chunkSize
		if pos < int64(chunkSize) {
			readSize = int(pos)
		}
		pos -= int64(readSize)

		if _, err := rs.Seek(pos, 0); err != nil {
			return err
		}
		n, err := rs.Read(buf[:readSize])
		if err != nil && err != io.EOF {
			return err
		}

		chunk := make([]byte, n+len(leftover))
		copy(chunk, buf[:n])
		copy(chunk[n:], leftover)

		for {
			idx := bytes.LastIndexByte(chunk, '\n')
			if idx == -1 {
				leftover = make([]byte, len(chunk))
				copy(leftover, chunk)
				break
			}
			
			line := chunk[idx+1:]
			if len(line) > 0 && line[len(line)-1] == '\r' {
				line = line[:len(line)-1]
			}
			lineCopy := make([]byte, len(line))
			copy(lineCopy, line)
			
			if !callback(lineCopy) {
				return nil
			}
			chunk = chunk[:idx]
		}
	}

	if len(leftover) > 0 {
		if leftover[len(leftover)-1] == '\r' {
			leftover = leftover[:len(leftover)-1]
		}
		callback(leftover)
	}

	return nil
}
