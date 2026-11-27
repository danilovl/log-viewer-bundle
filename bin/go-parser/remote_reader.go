package main

import (
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"os"
	"strconv"
	"strings"
	"time"
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

var httpClient = &http.Client{
	Transport: &http.Transport{
		Proxy:                 http.ProxyFromEnvironment,
		DialContext:           (&net.Dialer{Timeout: 10 * time.Second}).DialContext,
		TLSHandshakeTimeout:   10 * time.Second,
		ResponseHeaderTimeout: 30 * time.Second,
	},
	// Never forward credentials to another host on redirect.
	CheckRedirect: func(req *http.Request, via []*http.Request) error {
		if len(via) >= 5 {
			return fmt.Errorf("too many redirects")
		}
		if req.URL.Host != via[0].URL.Host {
			return fmt.Errorf("redirect to another host is not allowed: %s", req.URL.Host)
		}
		return nil
	},
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
		if !fi.Mode().IsRegular() {
			f.Close()
			return nil, 0, fmt.Errorf("not a regular file: %s", cfg.FilePath)
		}
		return f, fi.Size(), nil
	}

	switch cfg.Type {
	case "http", "https":
		req, err := buildHttpRequest(cfg)
		if err != nil {
			return nil, 0, err
		}
		resp, err := httpClient.Do(req)
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

// buildHttpRequest builds the request with credentials in the Authorization header,
// so special characters in user/password cannot alter the URL (host injection) and
// credentials never appear in error messages.
func buildHttpRequest(cfg RemoteConfig) (*http.Request, error) {
	scheme := cfg.Type
	if scheme == "" {
		scheme = "http"
	}
	if strings.ContainsAny(cfg.Host, "/@?#") {
		return nil, fmt.Errorf("invalid host: %q", cfg.Host)
	}

	host := cfg.Host
	if cfg.Port > 0 {
		host = net.JoinHostPort(cfg.Host, strconv.Itoa(cfg.Port))
	}

	path := cfg.FilePath
	if !strings.HasPrefix(path, "/") {
		path = "/" + path
	}

	u := &url.URL{Scheme: scheme, Host: host, Path: path}
	req, err := http.NewRequest(http.MethodGet, u.String(), nil)
	if err != nil {
		return nil, err
	}
	if cfg.User != "" {
		req.SetBasicAuth(cfg.User, cfg.Password)
	}
	return req, nil
}
