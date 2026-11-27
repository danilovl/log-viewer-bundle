package main

import (
	"bufio"
	"compress/gzip"
	"io"
	"strings"
)

func isGzipFile(path string) bool {
	return strings.HasSuffix(strings.ToLower(path), ".gz")
}

// newGzipReader returns a streaming gzip reader (multi-member archives supported).
// Files are never fully decompressed into memory.
func newGzipReader(r io.Reader) (*gzip.Reader, error) {
	return gzip.NewReader(bufio.NewReaderSize(r, 256*1024))
}

func stripGzExt(name string) string {
	if strings.HasSuffix(strings.ToLower(name), ".gz") {
		return name[:len(name)-3]
	}
	return name
}
