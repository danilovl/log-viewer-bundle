package main

import (
	"bytes"
	"strconv"
)

// Shared helpers for Combined/Common Log Format parsers (nginx, apache, access.log).
// Format: IP - IDENT [DD/Mon/YYYY:HH:MM:SS +ZZZZ] "METHOD /path HTTP/VER" STATUS SIZE "referer" "ua"

// detectAccessLog checks if the line matches the access log format.
func detectAccessLog(line []byte) bool {
	openB := bytes.IndexByte(line, '[')
	if openB < 3 {
		return false
	}
	closeB := bytes.IndexByte(line[openB:], ']')
	if closeB < 20 {
		return false
	}
	ts := line[openB+1 : openB+closeB]
	if len(ts) < 20 || ts[2] != '/' || ts[6] != '/' || ts[11] != ':' {
		return false
	}
	return bytes.IndexByte(line[openB+closeB:], '"') != -1
}

// extractAccessStatsFields extracts level, channel, hour from an access log line.
func extractAccessStatsFields(line []byte, channel string) (level, ch, hour string, ok bool) {
	openB := bytes.IndexByte(line, '[')
	if openB == -1 {
		return "", "", "", false
	}
	closeB := bytes.IndexByte(line[openB:], ']')
	if closeB < 20 {
		return "", "", "", false
	}
	ts := line[openB+1 : openB+closeB]
	if len(ts) < 15 || ts[2] != '/' || ts[6] != '/' {
		return "", "", "", false
	}

	mon := lookupMonth(ts[3:6])
	if mon == "" {
		return "", "", "", false
	}
	var buf [13]byte
	copy(buf[0:4], ts[7:11])
	buf[4] = '-'
	copy(buf[5:7], mon)
	buf[7] = '-'
	copy(buf[8:10], ts[0:2])
	buf[10] = 'T'
	copy(buf[11:13], ts[12:14])
	hour = string(buf[:])

	afterClose := line[openB+closeB+1:]
	q1 := bytes.IndexByte(afterClose, '"')
	if q1 == -1 {
		return "", "", "", false
	}
	q2 := bytes.IndexByte(afterClose[q1+1:], '"')
	if q2 == -1 {
		return "", "", "", false
	}
	rest := afterClose[q1+1+q2+1:]
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	spaceIdx := bytes.IndexByte(rest, ' ')
	if spaceIdx == -1 {
		spaceIdx = len(rest)
	}
	status, err := strconv.Atoi(unsafeString(rest[:spaceIdx]))
	if err != nil {
		return "", "", "", false
	}

	return statusToLevel(status), channel, hour, true
}

// parseAccessLogLine parses an access log line into a LogEntry.
func parseAccessLogLine(line []byte, entry *LogEntry, channelName string, full, parseTime bool) {
	entry.Channel = channelName

	openB := bytes.IndexByte(line, '[')
	if openB == -1 {
		entry.Level = "INFO"
		entry.Message = unsafeString(line)
		return
	}
	closeB := bytes.IndexByte(line[openB:], ']')
	if closeB == -1 {
		entry.Level = "INFO"
		entry.Message = unsafeString(line)
		return
	}

	entry.Timestamp = unsafeString(line[openB+1 : openB+closeB])

	ip := unsafeString(line[:bytes.IndexByte(line, ' ')])

	afterClose := line[openB+closeB+1:]
	q1 := bytes.IndexByte(afterClose, '"')
	var request string
	var restAfterReq []byte
	if q1 != -1 {
		q2 := bytes.IndexByte(afterClose[q1+1:], '"')
		if q2 != -1 {
			request = unsafeString(afterClose[q1+1 : q1+1+q2])
			restAfterReq = afterClose[q1+1+q2+1:]
		}
	}

	entry.Message = request

	rest := restAfterReq
	for len(rest) > 0 && rest[0] == ' ' {
		rest = rest[1:]
	}
	sp := bytes.IndexByte(rest, ' ')
	if sp != -1 {
		status, _ := strconv.Atoi(unsafeString(rest[:sp]))
		entry.Level = statusToLevel(status)
		if full {
			sizeEnd := bytes.IndexByte(rest[sp+1:], ' ')
			size := ""
			if sizeEnd != -1 {
				size = unsafeString(rest[sp+1 : sp+1+sizeEnd])
			}
			entry.Context = map[string]interface{}{
				"ip":     ip,
				"status": status,
				"size":   size,
			}
		}
	} else {
		entry.Level = "INFO"
	}

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
}
