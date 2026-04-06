package main

import "bytes"

// Shared helpers for RFC3339 syslog-style parsers (auth.log, kern.log, php-fpm.log).
// Format: 2026-03-29T00:05:43.791811+01:00 HOSTNAME APP[PID]: message
// or:     2026-03-29T00:05:43.791811+01:00 HOSTNAME APP: message

// detectRFC3339Syslog checks if a line starts with an RFC3339 timestamp followed by hostname + app.
func detectRFC3339Syslog(line []byte) bool {
	if len(line) < 25 {
		return false
	}
	// YYYY-MM-DDTHH:MM:SS
	return line[4] == '-' && line[7] == '-' && line[10] == 'T' &&
		line[13] == ':' && line[16] == ':'
}

// isNewRFC3339Entry checks if a line starts a new RFC3339 syslog entry.
func isNewRFC3339Entry(line []byte) bool {
	if len(line) < 20 {
		return false
	}
	return line[4] == '-' && line[7] == '-' && line[10] == 'T'
}

// extractRFC3339HourBucket extracts YYYY-MM-DDTHH from RFC3339 timestamp prefix.
func extractRFC3339HourBucket(line []byte) string {
	if len(line) < 13 {
		return ""
	}
	return unsafeString(line[:13])
}

// parseRFC3339SyslogFields extracts timestamp, hostname, app, and message from an RFC3339 syslog line.
func parseRFC3339SyslogFields(line []byte) (timestamp, hostname, app, message []byte) {
	// Find end of timestamp (first space after 'T')
	sp1 := bytes.IndexByte(line[10:], ' ')
	if sp1 == -1 {
		return line, nil, nil, nil
	}
	sp1 += 10
	timestamp = line[:sp1]

	rest := line[sp1+1:]
	// hostname
	sp2 := bytes.IndexByte(rest, ' ')
	if sp2 == -1 {
		return timestamp, rest, nil, nil
	}
	hostname = rest[:sp2]
	rest = rest[sp2+1:]

	// app[PID]: or app:
	colonIdx := bytes.IndexByte(rest, ':')
	if colonIdx == -1 {
		return timestamp, hostname, rest, nil
	}
	appTag := rest[:colonIdx]
	if bracket := bytes.IndexByte(appTag, '['); bracket != -1 {
		appTag = appTag[:bracket]
	}
	app = appTag

	msg := rest[colonIdx+1:]
	for len(msg) > 0 && msg[0] == ' ' {
		msg = msg[1:]
	}
	message = msg
	return
}

// ---------------------------------------------------------------------------
// AuthLogParser — auth.log format
// 2026-03-29T00:05:43.791811+01:00 HOSTNAME sudo: danilovl : message
// ---------------------------------------------------------------------------

type AuthLogParser struct{}

func NewAuthLogParser() *AuthLogParser { return &AuthLogParser{} }

func (p *AuthLogParser) Name() string { return "auth" }

func (p *AuthLogParser) Detect(line []byte) bool {
	if !detectRFC3339Syslog(line) {
		return false
	}
	// auth.log typically has sudo, sshd, pam_, login, etc.
	return bytes.Contains(line, []byte("sudo")) ||
		bytes.Contains(line, []byte("sshd")) ||
		bytes.Contains(line, []byte("pam_")) ||
		bytes.Contains(line, []byte("login")) ||
		bytes.Contains(line, []byte("auth"))
}

func (p *AuthLogParser) IsNewEntry(line []byte) bool { return isNewRFC3339Entry(line) }

func (p *AuthLogParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if !isNewRFC3339Entry(line) {
		return "", "", "", false
	}
	hour = extractRFC3339HourBucket(line)
	_, _, app, msg := parseRFC3339SyslogFields(line)
	channel = unsafeString(app)
	if channel == "" {
		channel = "auth"
	}
	level = syslogLevelFromMessage(msg)
	return level, channel, hour, true
}

func (p *AuthLogParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if !isNewRFC3339Entry(line) {
		entry.Level = "INFO"
		entry.Channel = "auth"
		entry.Message = unsafeString(line)
		return entry
	}

	ts, _, app, msg := parseRFC3339SyslogFields(line)
	entry.Timestamp = unsafeString(ts)
	entry.Channel = unsafeString(app)
	if entry.Channel == "" {
		entry.Channel = "auth"
	}
	entry.Message = unsafeString(msg)
	entry.Level = syslogLevelFromMessage(msg)

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}

// ---------------------------------------------------------------------------
// KernLogParser — kern.log format (same RFC3339 syslog structure)
// 2026-03-29T00:59:20.294161+01:00 DESKTOP-test kernel: message
// ---------------------------------------------------------------------------

type KernLogParser struct{}

func NewKernLogParser() *KernLogParser { return &KernLogParser{} }

func (p *KernLogParser) Name() string { return "kern" }

func (p *KernLogParser) Detect(line []byte) bool {
	if !detectRFC3339Syslog(line) {
		return false
	}
	return bytes.Contains(line, []byte("kernel:"))
}

func (p *KernLogParser) IsNewEntry(line []byte) bool { return isNewRFC3339Entry(line) }

func (p *KernLogParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if !isNewRFC3339Entry(line) {
		return "", "", "", false
	}
	hour = extractRFC3339HourBucket(line)
	_, _, app, msg := parseRFC3339SyslogFields(line)
	channel = unsafeString(app)
	if channel == "" {
		channel = "kernel"
	}
	level = syslogLevelFromMessage(msg)
	return level, channel, hour, true
}

func (p *KernLogParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if !isNewRFC3339Entry(line) {
		entry.Level = "INFO"
		entry.Channel = "kernel"
		entry.Message = unsafeString(line)
		return entry
	}

	ts, _, app, msg := parseRFC3339SyslogFields(line)
	entry.Timestamp = unsafeString(ts)
	entry.Channel = unsafeString(app)
	if entry.Channel == "" {
		entry.Channel = "kernel"
	}
	entry.Message = unsafeString(msg)
	entry.Level = syslogLevelFromMessage(msg)

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}

// ---------------------------------------------------------------------------
// PhpFpmParser — php-fpm log format (same RFC3339 syslog structure)
// 2026-03-29T00:59:20.294161+01:00 HOSTNAME php-fpm[1234]: message
// ---------------------------------------------------------------------------

type PhpFpmParser struct{}

func NewPhpFpmParser() *PhpFpmParser { return &PhpFpmParser{} }

func (p *PhpFpmParser) Name() string { return "php_fpm" }

func (p *PhpFpmParser) Detect(line []byte) bool {
	if !detectRFC3339Syslog(line) {
		return false
	}
	return bytes.Contains(line, []byte("php")) || bytes.Contains(line, []byte("fpm"))
}

func (p *PhpFpmParser) IsNewEntry(line []byte) bool { return isNewRFC3339Entry(line) }

func (p *PhpFpmParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if !isNewRFC3339Entry(line) {
		return "", "", "", false
	}
	hour = extractRFC3339HourBucket(line)
	_, _, app, msg := parseRFC3339SyslogFields(line)
	channel = unsafeString(app)
	if channel == "" {
		channel = "php-fpm"
	}
	level = syslogLevelFromMessage(msg)
	return level, channel, hour, true
}

func (p *PhpFpmParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if !isNewRFC3339Entry(line) {
		entry.Level = "INFO"
		entry.Channel = "php-fpm"
		entry.Message = unsafeString(line)
		return entry
	}

	ts, _, app, msg := parseRFC3339SyslogFields(line)
	entry.Timestamp = unsafeString(ts)
	entry.Channel = unsafeString(app)
	if entry.Channel == "" {
		entry.Channel = "php-fpm"
	}
	entry.Message = unsafeString(msg)
	entry.Level = syslogLevelFromMessage(msg)

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
