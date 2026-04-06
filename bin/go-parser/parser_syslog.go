package main

import "bytes"

// SyslogParser handles BSD syslog format:
// Mar 29 09:44:14 hostname app[1234]: message text
type SyslogParser struct{}

func NewSyslogParser() *SyslogParser { return &SyslogParser{} }

func (p *SyslogParser) Name() string { return "syslog" }

func (p *SyslogParser) Detect(line []byte) bool {
	// Mon DD HH:MM:SS hostname ...
	if len(line) < 16 {
		return false
	}
	if !isUpperAlpha(line[0]) || !isLowerAlpha(line[1]) || !isLowerAlpha(line[2]) || line[3] != ' ' {
		return false
	}
	mon := lookupMonth(line[:3])
	if mon == "" {
		return false
	}
	// DD (space-padded or zero-padded)
	if line[4] != ' ' && !isDigit(line[4]) {
		return false
	}
	if !isDigit(line[5]) {
		return false
	}
	if line[6] != ' ' {
		return false
	}
	// HH:MM:SS
	return line[9] == ':' && line[12] == ':' && isDigit(line[7]) && isDigit(line[8])
}

func isUpperAlpha(c byte) bool { return c >= 'A' && c <= 'Z' }
func isLowerAlpha(c byte) bool { return c >= 'a' && c <= 'z' }
func isDigit(c byte) bool      { return c >= '0' && c <= '9' }

func (p *SyslogParser) IsNewEntry(line []byte) bool {
	if len(line) < 16 {
		return false
	}
	return isUpperAlpha(line[0]) && isLowerAlpha(line[1]) && isLowerAlpha(line[2]) &&
		line[3] == ' ' && line[6] == ' ' && line[9] == ':' && line[12] == ':'
}

func (p *SyslogParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 16 {
		return "", "", "", false
	}

	mon := lookupMonth(line[:3])
	if mon == "" {
		return "", "", "", false
	}

	// Build hour: YYYY-MM-DDTHH (we don't know the year, use current)
	// Syslog doesn't include year — use "0000" as placeholder
	var buf [13]byte
	buf[0] = '0'
	buf[1] = '0'
	buf[2] = '0'
	buf[3] = '0'
	buf[4] = '-'
	copy(buf[5:7], mon)
	buf[7] = '-'
	// DD: line[4:6], may be " 1" → "01"
	if line[4] == ' ' {
		buf[8] = '0'
	} else {
		buf[8] = line[4]
	}
	buf[9] = line[5]
	buf[10] = 'T'
	copy(buf[11:13], line[7:9]) // HH
	hour = string(buf[:])

	// Find channel: hostname app[PID]: or hostname app:
	// Skip past "Mon DD HH:MM:SS " (16 bytes)
	rest := line[16:]
	sp := bytes.IndexByte(rest, ' ')
	if sp == -1 {
		return "", "", "", false
	}
	rest = rest[sp+1:]
	// rest is "app[PID]: message" or "app: message"
	bracketOrColon := bytes.IndexAny(rest, "[:")
	if bracketOrColon == -1 {
		return "", "", "", false
	}
	channel = unsafeString(rest[:bracketOrColon])

	// Syslog doesn't have explicit levels; derive from keywords
	level = syslogLevelFromMessage(line)

	return level, channel, hour, true
}

func syslogLevelFromMessage(line []byte) string {
	lower := bytes.ToLower(line)
	switch {
	case bytes.Contains(lower, []byte("error")),
		bytes.Contains(lower, []byte("fatal")),
		bytes.Contains(lower, []byte("panic")),
		bytes.Contains(lower, []byte("crit")):
		return "ERROR"
	case bytes.Contains(lower, []byte("warn")):
		return "WARNING"
	case bytes.Contains(lower, []byte("debug")):
		return "DEBUG"
	default:
		return "INFO"
	}
}

func (p *SyslogParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if len(line) < 16 || line[3] != ' ' || line[6] != ' ' || line[9] != ':' {
		entry.Level = "INFO"
		entry.Channel = "syslog"
		entry.Message = unsafeString(line)
		return entry
	}

	// Timestamp: "Mar 29 09:44:14"
	entry.Timestamp = unsafeString(line[:15])
	entry.Level = syslogLevelFromMessage(line)

	// Skip past "Mon DD HH:MM:SS "
	rest := line[16:]
	// hostname
	sp := bytes.IndexByte(rest, ' ')
	if sp == -1 {
		entry.Channel = "syslog"
		entry.Message = unsafeString(rest)
		return entry
	}
	rest = rest[sp+1:]

	// app[PID]: or app:
	colonIdx := bytes.IndexByte(rest, ':')
	if colonIdx == -1 {
		entry.Channel = "syslog"
		entry.Message = unsafeString(rest)
		return entry
	}
	tag := rest[:colonIdx]
	// Strip [PID] from tag
	if bracket := bytes.IndexByte(tag, '['); bracket != -1 {
		tag = tag[:bracket]
	}
	entry.Channel = unsafeString(tag)

	msg := rest[colonIdx+1:]
	for len(msg) > 0 && msg[0] == ' ' {
		msg = msg[1:]
	}
	entry.Message = unsafeString(msg)

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
