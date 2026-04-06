package main

import "bytes"

// MysqlParser handles MySQL error/general log format:
// 2020-08-06T14:25:02.835618Z 0 [Note] [MY-012487] [InnoDB] DDL log recovery : begin
// 2020-08-06T14:25:02.936146Z 0 [Warning] [MY-010068] [Server] message

type MysqlParser struct{}

func NewMysqlParser() *MysqlParser { return &MysqlParser{} }

func (p *MysqlParser) Name() string { return "mysql" }

func (p *MysqlParser) Detect(line []byte) bool {
	// YYYY-MM-DDTHH:MM:SS.ffffffZ TID [Level]
	if len(line) < 30 {
		return false
	}
	if line[4] != '-' || line[7] != '-' || line[10] != 'T' || line[13] != ':' || line[16] != ':' {
		return false
	}
	// Must have [Level] bracket after timestamp + thread ID
	bracketIdx := bytes.IndexByte(line[20:], '[')
	return bracketIdx != -1
}

func (p *MysqlParser) IsNewEntry(line []byte) bool {
	if len(line) < 20 {
		return false
	}
	return line[4] == '-' && line[7] == '-' && line[10] == 'T' && line[13] == ':'
}

func (p *MysqlParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 30 || line[10] != 'T' {
		return "", "", "", false
	}

	// hour: YYYY-MM-DDTHH
	hour = unsafeString(line[:13])

	// Find first [Level]
	bracketIdx := bytes.IndexByte(line[20:], '[')
	if bracketIdx == -1 {
		return "", "", "", false
	}
	start := 20 + bracketIdx + 1
	closeIdx := bytes.IndexByte(line[start:], ']')
	if closeIdx == -1 {
		return "", "", "", false
	}
	level = normalizeMysqlLevel(unsafeString(line[start : start+closeIdx]))

	// Find category [Category] — second bracket pair
	channel = "mysql"
	rest := line[start+closeIdx+1:]
	if len(rest) > 2 && rest[0] == ' ' && rest[1] == '[' {
		// skip [MY-XXXXXX] code block
		close2 := bytes.IndexByte(rest[2:], ']')
		if close2 != -1 {
			rest2 := rest[2+close2+1:]
			if len(rest2) > 2 && rest2[0] == ' ' && rest2[1] == '[' {
				close3 := bytes.IndexByte(rest2[2:], ']')
				if close3 != -1 {
					channel = unsafeString(rest2[2 : 2+close3])
				}
			}
		}
	}

	return level, channel, hour, true
}

func normalizeMysqlLevel(level string) string {
	switch level {
	case "Note", "note", "NOTE":
		return "INFO"
	case "Warning", "warning", "WARNING":
		return "WARNING"
	case "Error", "error", "ERROR":
		return "ERROR"
	case "System", "system", "SYSTEM":
		return "INFO"
	default:
		return toUpperASCIIString(level)
	}
}

func (p *MysqlParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	entry.Channel = "mysql"

	if len(line) < 30 || line[10] != 'T' {
		entry.Level = "INFO"
		entry.Message = unsafeString(line)
		return entry
	}

	// Find end of timestamp (first space after second ':')
	tsEnd := bytes.IndexByte(line[19:], ' ')
	if tsEnd == -1 {
		entry.Level = "INFO"
		entry.Timestamp = unsafeString(line[:19])
		entry.Message = unsafeString(line[19:])
		return entry
	}
	tsEnd += 19
	entry.Timestamp = unsafeString(line[:tsEnd])

	rest := line[tsEnd+1:]
	sp := bytes.IndexByte(rest, ' ')
	if sp == -1 {
		entry.Level = "INFO"
		entry.Message = unsafeString(rest)
		return entry
	}
	rest = rest[sp+1:]

	// Parse [Level]
	if len(rest) > 0 && rest[0] == '[' {
		closeIdx := bytes.IndexByte(rest[1:], ']')
		if closeIdx != -1 {
			entry.Level = normalizeMysqlLevel(unsafeString(rest[1 : 1+closeIdx]))
			rest = rest[1+closeIdx+1:]
		} else {
			entry.Level = "INFO"
		}
	} else {
		entry.Level = "INFO"
	}

	// Skip optional [MY-XXXXXX] and [Category]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	// [MY-XXXXXX]
	if len(rest) > 0 && rest[0] == '[' {
		closeIdx := bytes.IndexByte(rest[1:], ']')
		if closeIdx != -1 {
			rest = rest[1+closeIdx+1:]
			for len(rest) > 0 && rest[0] == ' ' {
				rest = rest[1:]
			}
		}
	}
	// [Category]
	if len(rest) > 0 && rest[0] == '[' {
		closeIdx := bytes.IndexByte(rest[1:], ']')
		if closeIdx != -1 {
			if full {
				entry.Channel = unsafeString(rest[1 : 1+closeIdx])
			}
			rest = rest[1+closeIdx+1:]
			for len(rest) > 0 && rest[0] == ' ' {
				rest = rest[1:]
			}
		}
	}

	entry.Message = unsafeString(rest)

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
