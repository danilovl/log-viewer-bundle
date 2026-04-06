package main

import (
	"fmt"
	"io"
	"net/http"
	"os"
	"strings"
)

type RemoteConfig struct {
	FilePath string
	Host     string
	Port     int
	User     string
	Password string
	SSHKey   string
	Type     string
}

func OpenLogFile(cfg RemoteConfig) (io.ReadCloser, int64, error) {
	if cfg.Host == "" {
		f, err := os.Open(cfg.FilePath)
		if err != nil {
			return nil, 0, err
		}
		fi, err := f.Stat()
		if err != nil {
			f.Close()
			return nil, 0, err
		}
		return f, fi.Size(), nil
	}

	switch cfg.Type {
	case "http", "https":
		url := buildHttpUrl(cfg)
		resp, err := http.Get(url)
		if err != nil {
			return nil, 0, err
		}
		if resp.StatusCode != http.StatusOK {
			resp.Body.Close()
			return nil, 0, fmt.Errorf("HTTP error: %s", resp.Status)
		}
		return resp.Body, resp.ContentLength, nil
	case "ssh", "sftp":
		return nil, 0, fmt.Errorf("SFTP/SSH is not implemented yet in Go parser. Please use local files or HTTP for now.")
	default:
		return nil, 0, fmt.Errorf("unsupported host type: %s", cfg.Type)
	}
}

func buildHttpUrl(cfg RemoteConfig) string {
	protocol := cfg.Type
	if protocol == "" {
		protocol = "http"
	}

	url := protocol + "://"
	if cfg.User != "" && cfg.Password != "" {
		url += cfg.User + ":" + cfg.Password + "@"
	} else if cfg.User != "" {
		url += cfg.User + "@"
	}

	url += cfg.Host
	if cfg.Port > 0 {
		url += fmt.Sprintf(":%d", cfg.Port)
	}

	path := cfg.FilePath
	if !strings.HasPrefix(path, "/") {
		path = "/" + path
	}
	url += path

	return url
}
