package main

import (
	"bufio"
	"compress/gzip"
	"flag"
	"fmt"
	"io"
	"os"
	"regexp"
)

func main() {
	filePath := flag.String("file", "", "Path to the log file (required)")
	pattern := flag.String("pattern", "", "Regexp pattern for parsing")
	parserName := flag.String("parser", "", "Parser type: monolog, nginx_access, nginx_error, syslog, json (auto-detect if omitted)")
	limit := flag.Int("limit", 50, "Maximum number of entries to return")
	offset := flag.Int("offset", 0, "Number of entries to skip")
	cursor := flag.String("cursor", "", "Cursor (timestamp of the last entry to load older entries)")
	level := flag.String("level", "", "Filter by log level (ERROR, WARNING, etc.)")
	channel := flag.String("channel", "", "Filter by channel")
	levels := flag.String("levels", "", "Filter by log levels (comma separated, e.g., ERROR,WARNING)")
	search := flag.String("search", "", "Search string in message or SQL")
	searchRegex := flag.Bool("search-regex", false, "Whether to use regex for search")
	searchCaseSensitive := flag.Bool("search-case-sensitive", false, "Whether to use case sensitive search")
	dateFrom := flag.String("date-from", "", "Filter by date from (RFC3339 or YYYY-MM-DD HH:MM:SS)")
	dateTo := flag.String("date-to", "", "Filter by date to (RFC3339 or YYYY-MM-DD HH:MM:SS)")
	sort := flag.String("sort", "desc", "Sort direction (asc, desc)")
	mode := flag.String("mode", "logs", "Mode: logs, stat_dashboard, stat_log (aliases: stats, stat_filter)")

	host := flag.String("host", "", "Remote host")
	port := flag.Int("port", 0, "Remote port")
	user := flag.String("user", "", "Remote user")
	password := flag.String("password", "", "Remote password")
	sshKey := flag.String("ssh-key", "", "Remote ssh key")
	hostType := flag.String("host-type", "", "Remote host type (ssh, sftp, http, https)")

	flag.Parse()

	if *filePath == "" {
		fmt.Println("Usage: ./log-parser --file /path/to/log [--parser type] [--pattern \"regexp\"] [--limit 50] [--cursor timestamp] [--level ERROR] [--channel app] [--search query] [--sort desc] [--mode logs|stats|stat_filter]")
		os.Exit(1)
	}

	var parser LogParser

	if *parserName != "" {
		factory, ok := parserRegistry[*parserName]
		if !ok {
			fmt.Fprintf(os.Stderr, "Unknown parser: %s. Available: monolog, nginx_access, nginx_error, apache_access, syslog, auth, kern, php_fpm, php_error, supervisord, mysql, json\n", *parserName)
			os.Exit(1)
		}
		parser = factory()
	} else if *pattern != "" {
		pStr := *pattern
		if len(pStr) > 2 {
			first := pStr[0]
			last := pStr[len(pStr)-1]
			if first == last && (first == '~' || first == '/' || first == '#') {
				pStr = pStr[1 : len(pStr)-1]
			}
		}
		re, err := regexp.Compile(pStr)
		if err != nil {
			fmt.Fprintf(os.Stderr, "Invalid pattern: %v\n", err)
			os.Exit(1)
		}
		parser = NewUniversalParser(re)
	} else {
		parser = autoDetectParser(*filePath)
		if parser == nil {
			fmt.Fprintf(os.Stderr, "Could not auto-detect log format. Use --parser or --pattern.\n")
			os.Exit(1)
		}
	}

	cfg := RemoteConfig{
		FilePath: *filePath,
		Host:     *host,
		Port:     *port,
		User:     *user,
		Password: *password,
		SSHKey:   *sshKey,
		Type:     *hostType,
	}

	switch *mode {
	case "stat_dashboard", "stats":
		runStatsDashboard(cfg, parser, *dateFrom, *dateTo)
	case "stat_log", "stat_filter":
		runStatsFilter(cfg, parser, *level, *levels, *channel, *search, *searchRegex, *searchCaseSensitive, *dateFrom, *dateTo)
	case "count":
		runCount(cfg, parser, *level, *levels, *channel, *search, *searchRegex, *searchCaseSensitive, *dateFrom, *dateTo)
	default:
		runLogs(cfg, parser, *limit, *offset, *cursor, *level, *levels, *channel, *search, *searchRegex, *searchCaseSensitive, *dateFrom, *dateTo, *sort)
	}
}

func autoDetectParser(filePath string) LogParser {
	var reader io.Reader

	f, err := os.Open(filePath)
	if err != nil {
		return nil
	}
	defer f.Close()

	if isGzipFile(filePath) {
		gr, err := gzip.NewReader(f)
		if err != nil {
			return nil
		}
		defer gr.Close()
		reader = gr
	} else {
		reader = f
	}

	scanner := bufio.NewScanner(reader)
	buf := make([]byte, 0, 64*1024)
	scanner.Buffer(buf, 1024*1024)

	for i := 0; i < 10 && scanner.Scan(); i++ {
		line := scanner.Bytes()
		if len(line) == 0 {
			continue
		}
		if p := detectParserFromLine(line); p != nil {
			return p
		}
	}
	return nil
}
