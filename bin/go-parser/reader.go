package main

import (
	"bufio"
	"bytes"
	"fmt"
	"io"
)

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
