package main

import (
	"bytes"
	"compress/gzip"
	"io"
	"os"
	"strings"
)

func isGzipFile(path string) bool {
	return strings.HasSuffix(strings.ToLower(path), ".gz")
}

func readGzipFull(path string) ([]byte, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	gr, err := gzip.NewReader(f)
	if err != nil {
		return nil, err
	}
	defer gr.Close()

	var buf bytes.Buffer
	if _, err := io.Copy(&buf, gr); err != nil {
		return nil, err
	}
	return buf.Bytes(), nil
}

func stripGzExt(name string) string {
	if strings.HasSuffix(strings.ToLower(name), ".gz") {
		return name[:len(name)-3]
	}
	return name
}
