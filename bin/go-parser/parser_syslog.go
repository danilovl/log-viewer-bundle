package main

import (
	"bytes"
	"strconv"
	"time"
)

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
	// BSD Syslog: Mon DD HH:MM:SS
	if isUpperAlpha(line[0]) && isLowerAlpha(line[1]) && isLowerAlpha(line[2]) &&
		line[3] == ' ' && line[6] == ' ' && line[9] == ':' && line[12] == ':' {
		return true
	}
	// RFC3339: 2026-03-29T...
	if len(line) >= 20 && line[4] == '-' && line[7] == '-' && (line[10] == 'T' || line[10] == ' ') {
		return true
	}
	return false
}

func (p *SyslogParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 16 {
		return "", "", "", false
	}

	// BSD Syslog: Mon DD HH:MM:SS
	if isUpperAlpha(line[0]) && isLowerAlpha(line[1]) && isLowerAlpha(line[2]) && line[3] == ' ' {
		mon := lookupMonth(line[:3])
		if mon != "" && line[6] == ' ' && line[9] == ':' && line[12] == ':' {
			// Build hour: YYYY-MM-DDTHH (we don't know the year, use current)
			now := time.Now()
			var buf [13]byte
			yearStr := strconv.Itoa(now.Year())
			copy(buf[0:4], yearStr)
			buf[4] = '-'
			copy(buf[5:7], mon)
			buf[7] = '-'
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
			rest := line[16:]
			sp := bytes.IndexByte(rest, ' ')
			if sp != -1 {
				rest = rest[sp+1:]
				bracketOrColon := bytes.IndexAny(rest, "[:")
				if bracketOrColon != -1 {
					channel = unsafeString(rest[:bracketOrColon])
				}
			}
			if channel == "" {
				channel = "syslog"
			}
			level = syslogLevelFromMessage(line)
			return level, channel, hour, true
		}
	}

	// RFC3339: 2026-03-29T...
	if len(line) >= 20 && line[4] == '-' && line[7] == '-' && (line[10] == 'T' || line[10] == ' ') {
		hour = unsafeString(line[:13])
		if line[10] == ' ' {
			hour = unsafeString(line[:10]) + "T" + unsafeString(line[11:13])
		}
		
		// 2026-03-29T00:05:43.791811+01:00 DESKTOP-test sudo: ...
		rest := line[20:]
		// Find end of timestamp
		sp1 := bytes.IndexByte(rest, ' ')
		if sp1 != -1 {
			rest = rest[sp1+1:] // hostname
			sp2 := bytes.IndexByte(rest, ' ')
			if sp2 != -1 {
				rest = rest[sp2+1:] // app: ...
				colon := bytes.IndexByte(rest, ':')
				if colon != -1 {
					tag := rest[:colon]
					if br := bytes.IndexByte(tag, '['); br != -1 {
						tag = tag[:br]
					}
					channel = unsafeString(tag)
				}
			}
		}
		if channel == "" {
			channel = "syslog"
		}
		level = syslogLevelFromMessage(line)
		return level, channel, hour, true
	}

	return "", "", "", false
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

	// BSD Syslog: Mon DD HH:MM:SS
	if isUpperAlpha(line[0]) && isLowerAlpha(line[1]) && isLowerAlpha(line[2]) && line[3] == ' ' {
		if len(line) >= 15 {
			entry.Timestamp = unsafeString(line[:15])
			entry.Level = syslogLevelFromMessage(line)
			// hostname app[PID]: or hostname app:
			rest := line[16:]
			sp := bytes.IndexByte(rest, ' ')
			if sp != -1 {
				rest = rest[sp+1:]
				colon := bytes.IndexByte(rest, ':')
				if colon != -1 {
					tag := rest[:colon]
					if br := bytes.IndexByte(tag, '['); br != -1 {
						tag = tag[:br]
					}
					entry.Channel = unsafeString(tag)
					msg := rest[colon+1:]
					for len(msg) > 0 && msg[0] == ' ' {
						msg = msg[1:]
					}
					entry.Message = unsafeString(msg)
				}
			}
		}
	} else if len(line) >= 20 && line[4] == '-' && line[7] == '-' && (line[10] == 'T' || line[10] == ' ') {
		// RFC3339: 2026-03-29T...
		sp := bytes.IndexByte(line, ' ')
		if sp != -1 {
			entry.Timestamp = unsafeString(line[:sp])
			rest := line[sp+1:] // hostname
			sp2 := bytes.IndexByte(rest, ' ')
			if sp2 != -1 {
				rest = rest[sp2+1:] // app:
				colon := bytes.IndexByte(rest, ':')
				if colon != -1 {
					tag := rest[:colon]
					if br := bytes.IndexByte(tag, '['); br != -1 {
						tag = tag[:br]
					}
					entry.Channel = unsafeString(tag)
					msg := rest[colon+1:]
					for len(msg) > 0 && msg[0] == ' ' {
						msg = msg[1:]
					}
					entry.Message = unsafeString(msg)
				}
			}
		}
		entry.Level = syslogLevelFromMessage(line)
	}

	if entry.Channel == "" {
		entry.Channel = "syslog"
	}
	if entry.Message == "" {
		entry.Message = unsafeString(line)
	}
	if entry.Level == "" {
		entry.Level = "INFO"
	}

	if parseTime && entry.Timestamp != "" {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
