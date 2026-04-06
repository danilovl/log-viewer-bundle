package main

import "bytes"

// ---------------------------------------------------------------------------
// NginxErrorParser — Nginx error log
// 2015/06/11 09:44:14 [error] 1234#5678: *9 message text
// ---------------------------------------------------------------------------

type NginxErrorParser struct{}

func NewNginxErrorParser() *NginxErrorParser { return &NginxErrorParser{} }

func (p *NginxErrorParser) Name() string { return "nginx_error" }

func (p *NginxErrorParser) Detect(line []byte) bool {
	if len(line) < 25 {
		return false
	}
	if line[4] != '/' || line[7] != '/' || line[10] != ' ' || line[13] != ':' || line[16] != ':' {
		return false
	}
	return line[19] == ' ' && line[20] == '['
}

func (p *NginxErrorParser) IsNewEntry(line []byte) bool {
	if len(line) < 20 {
		return false
	}
	return line[4] == '/' && line[7] == '/' && line[10] == ' '
}

func (p *NginxErrorParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 25 || line[4] != '/' || line[10] != ' ' || line[20] != '[' {
		return "", "", "", false
	}

	var buf [13]byte
	copy(buf[0:4], line[0:4])
	buf[4] = '-'
	copy(buf[5:7], line[5:7])
	buf[7] = '-'
	copy(buf[8:10], line[8:10])
	buf[10] = 'T'
	copy(buf[11:13], line[11:13])
	hour = string(buf[:])

	closeBracket := bytes.IndexByte(line[21:], ']')
	if closeBracket == -1 {
		return "", "", "", false
	}
	level = toUpperASCIIString(unsafeString(line[21 : 21+closeBracket]))

	return level, "nginx", hour, true
}

func (p *NginxErrorParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	entry.Channel = "nginx"

	if len(line) < 25 || line[4] != '/' || line[20] != '[' {
		entry.Level = "ERROR"
		entry.Message = unsafeString(line)
		return entry
	}

	entry.Timestamp = unsafeString(line[0:19])

	closeBracket := bytes.IndexByte(line[21:], ']')
	if closeBracket == -1 {
		entry.Level = "ERROR"
		entry.Message = unsafeString(line[20:])
		return entry
	}
	entry.Level = toUpperASCIIString(unsafeString(line[21 : 21+closeBracket]))

	msg := line[21+closeBracket+1:]
	for len(msg) > 0 && msg[0] == ' ' {
		msg = msg[1:]
	}
	colonIdx := bytes.IndexByte(msg, ':')
	if colonIdx != -1 && colonIdx < 20 {
		afterColon := msg[colonIdx+1:]
		for len(afterColon) > 0 && afterColon[0] == ' ' {
			afterColon = afterColon[1:]
		}
		if len(afterColon) > 0 && afterColon[0] == '*' {
			sp := bytes.IndexByte(afterColon, ' ')
			if sp != -1 {
				afterColon = afterColon[sp+1:]
			}
		}
		entry.Message = unsafeString(afterColon)
	} else {
		entry.Message = unsafeString(msg)
	}

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}
