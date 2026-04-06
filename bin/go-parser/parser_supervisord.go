package main

import "bytes"

// SupervisordParser handles supervisord log format:
// 2026-03-27 21:04:56,966 CRIT message text
// 2026-03-27 21:04:56,966 INFO spawned: 'program' with pid 1234

type SupervisordParser struct{}

func NewSupervisordParser() *SupervisordParser { return &SupervisordParser{} }

func (p *SupervisordParser) Name() string { return "supervisord" }

func (p *SupervisordParser) Detect(line []byte) bool {
	// YYYY-MM-DD HH:MM:SS,mmm LEVEL
	if len(line) < 24 {
		return false
	}
	return line[4] == '-' && line[7] == '-' && line[10] == ' ' &&
		line[13] == ':' && line[16] == ':' && line[19] == ','
}

func (p *SupervisordParser) IsNewEntry(line []byte) bool {
	if len(line) < 20 {
		return false
	}
	return line[4] == '-' && line[7] == '-' && line[10] == ' ' &&
		line[13] == ':' && line[16] == ':' && line[19] == ','
}

func (p *SupervisordParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 24 || line[19] != ',' {
		return "", "", "", false
	}

	// hour bucket: YYYY-MM-DDTHH
	var buf [13]byte
	copy(buf[0:10], line[0:10]) // YYYY-MM-DD
	buf[10] = 'T'
	copy(buf[11:13], line[11:13]) // HH
	hour = string(buf[:])

	// Level starts after "YYYY-MM-DD HH:MM:SS,mmm "
	rest := line[23:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	sp := bytes.IndexByte(rest, ' ')
	if sp == -1 {
		level = toUpperASCIIString(unsafeString(rest))
	} else {
		level = toUpperASCIIString(unsafeString(rest[:sp]))
	}
	level = normalizeSupervisordLevel(level)

	return level, "supervisord", hour, true
}

func normalizeSupervisordLevel(level string) string {
	switch level {
	case "CRIT", "CRITICAL", "FATAL":
		return "ERROR"
	case "WARN":
		return "WARNING"
	case "INFO", "DEBUG", "ERROR", "WARNING":
		return level
	default:
		return "INFO"
	}
}

func (p *SupervisordParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	entry.Channel = "supervisord"

	if len(line) < 24 || line[19] != ',' {
		entry.Level = "INFO"
		entry.Message = unsafeString(line)
		return entry
	}

	// Timestamp: "2026-03-27 21:04:56,966"
	entry.Timestamp = unsafeString(line[:23])

	rest := line[23:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}

	sp := bytes.IndexByte(rest, ' ')
	if sp == -1 {
		entry.Level = normalizeSupervisordLevel(toUpperASCIIString(unsafeString(rest)))
		entry.Message = ""
		return entry
	}

	entry.Level = normalizeSupervisordLevel(toUpperASCIIString(unsafeString(rest[:sp])))
	entry.Message = unsafeString(rest[sp+1:])

	if parseTime {
		// supervisord uses comma before ms: "2026-03-27 21:04:56,966" → replace with dot
		ts := entry.Timestamp
		if len(ts) > 19 && ts[19] == ',' {
			ts = ts[:19] + "." + ts[20:]
		}
		entry.Time = parseTimestamp(ts)
	}
	return entry
}
