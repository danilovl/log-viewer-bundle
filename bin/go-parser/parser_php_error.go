package main

import "bytes"

// PhpErrorParser handles PHP error log format:
// [2026-03-29T09:44:14.945778+00:00] PHP Fatal error:  message in /path on line N
// [2026-03-29T09:44:15.945778+00:00] PHP Warning:  message in /path on line N

type PhpErrorParser struct{}

func NewPhpErrorParser() *PhpErrorParser { return &PhpErrorParser{} }

func (p *PhpErrorParser) Name() string { return "php_error" }

func (p *PhpErrorParser) Detect(line []byte) bool {
	if len(line) < 10 || line[0] != '[' {
		return false
	}
	endTs := bytes.IndexByte(line, ']')
	if endTs < 10 {
		return false
	}
	rest := line[endTs+1:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	return bytes.HasPrefix(rest, []byte("PHP "))
}

func (p *PhpErrorParser) IsNewEntry(line []byte) bool {
	return len(line) > 0 && line[0] == '['
}

func (p *PhpErrorParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 10 || line[0] != '[' {
		return "", "", "", false
	}
	endTs := bytes.IndexByte(line, ']')
	if endTs < 10 {
		return "", "", "", false
	}

	ts := line[1:endTs]
	hour = parseHourBucket(unsafeString(ts))

	rest := line[endTs+1:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	if !bytes.HasPrefix(rest, []byte("PHP ")) {
		return "", "", "", false
	}
	rest = rest[4:] // skip "PHP "

	level = extractPhpErrorLevel(rest)
	return level, "php", hour, true
}

func extractPhpErrorLevel(rest []byte) string {
	colonIdx := bytes.IndexByte(rest, ':')
	if colonIdx == -1 {
		return "ERROR"
	}
	switch {
	case bytes.HasPrefix(rest, []byte("Fatal error")),
		bytes.HasPrefix(rest, []byte("Parse error")):
		return "ERROR"
	case bytes.HasPrefix(rest, []byte("Warning")):
		return "WARNING"
	case bytes.HasPrefix(rest, []byte("Notice")),
		bytes.HasPrefix(rest, []byte("Deprecated")):
		return "INFO"
	case bytes.HasPrefix(rest, []byte("Strict")):
		return "DEBUG"
	default:
		return "ERROR"
	}
}

func (p *PhpErrorParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	entry.Channel = "php"

	if len(line) < 10 || line[0] != '[' {
		entry.Level = "ERROR"
		entry.Message = unsafeString(line)
		return entry
	}

	endTs := bytes.IndexByte(line, ']')
	if endTs == -1 {
		entry.Level = "ERROR"
		entry.Message = unsafeString(line)
		return entry
	}

	entry.Timestamp = unsafeString(line[1:endTs])

	rest := line[endTs+1:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}

	if bytes.HasPrefix(rest, []byte("PHP ")) {
		rest = rest[4:]
	}

	entry.Level = extractPhpErrorLevel(rest)

	// Message is everything after "Level: "
	colonIdx := bytes.IndexByte(rest, ':')
	if colonIdx != -1 {
		msg := rest[colonIdx+1:]
		for len(msg) > 0 && msg[0] == ' ' {
			msg = msg[1:]
		}
		entry.Message = unsafeString(msg)
	} else {
		entry.Message = unsafeString(rest)
	}

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
