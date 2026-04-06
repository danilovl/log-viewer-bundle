package main

import (
	"bytes"
	"encoding/json"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

var (
	lastLayout atomic.Value
	timestampLayouts = [...]string{
		time.RFC3339Nano,
		"2006-01-02T15:04:05.000000Z07:00",
		"2006-01-02 15:04:05",
		"2006-01-02 15:04:05.000000",
		time.RFC3339,
		"02/Jan/2006:15:04:05 -0700",
		"2006-01-02T15:04:05.000000Z",
		"2015/01/02 15:04:05",
	}
	entryPool = sync.Pool{
		New: func() interface{} {
			return &LogEntry{}
		},
	}
)

type LogEntry struct {
	Timestamp      string                 `json:"timestamp"`
	Level          string                 `json:"level"`
	Channel        string                 `json:"channel"`
	Message        string                 `json:"message"`
	SQL            string                 `json:"sql,omitempty"`
	Parameters     map[string]interface{} `json:"parameters,omitempty"`
	ExceptionClass string                 `json:"exceptionClass,omitempty"`
	File           string                 `json:"file"`
	Context        map[string]interface{} `json:"context,omitempty"`
	Time           time.Time              `json:"-"`
}

func (e *LogEntry) Reset() {
	e.Timestamp = ""
	e.Level = ""
	e.Channel = ""
	e.Message = ""
	e.SQL = ""
	e.Parameters = nil
	e.ExceptionClass = ""
	e.File = ""
	e.Context = nil
	e.Time = time.Time{}
}

func PutEntry(e *LogEntry) {
	if e != nil {
		e.Reset()
		entryPool.Put(e)
	}
}

type UniversalParser struct {
	re             *regexp.Regexp
	timestampIdx   int
	levelIdx       int
	channelIdx     int
	messageIdx     int
	fileIdx        int
	contextIdx     int
	extraIdx       int
	statusIdx      int
	requestIdx     int
	remoteAddrIdx  int
	bodySizeIdx    int
	refererIdx     int
	userAgentIdx   int
	useFastPath    bool
	isAccessLog    bool
}

func NewUniversalParser(re *regexp.Regexp) *UniversalParser {
	pattern := re.String()
	if !strings.HasPrefix(pattern, "(?s)") {
		if newRe, err := regexp.Compile("(?s)" + pattern); err == nil {
			re = newRe
		}
	}

	p := &UniversalParser{
		re:            re,
		timestampIdx:  -1,
		levelIdx:      -1,
		channelIdx:    -1,
		messageIdx:    -1,
		fileIdx:       -1,
		contextIdx:    -1,
		statusIdx:     -1,
		requestIdx:    -1,
		remoteAddrIdx: -1,
		bodySizeIdx:   -1,
		refererIdx:    -1,
		userAgentIdx:  -1,
	}

	pattern = re.String()
	if strings.Contains(pattern, "timestamp") && strings.Contains(pattern, "channel") &&
		strings.Contains(pattern, "level") && strings.HasPrefix(pattern, "^\\[") {
		p.useFastPath = true
	}

	for i, name := range re.SubexpNames() {
		switch name {
		case "timestamp":
			p.timestampIdx = i
		case "level":
			p.levelIdx = i
		case "channel":
			p.channelIdx = i
		case "message":
			p.messageIdx = i
		case "file":
			p.fileIdx = i
		case "context":
			p.contextIdx = i
		case "extra":
			p.extraIdx = i
		case "status":
			p.statusIdx = i
		case "request":
			p.requestIdx = i
		case "remote_addr":
			p.remoteAddrIdx = i
		case "body_bytes_sent":
			p.bodySizeIdx = i
		case "http_referer":
			p.refererIdx = i
		case "http_user_agent":
			p.userAgentIdx = i
		}
	}

	if p.statusIdx >= 0 && p.timestampIdx >= 0 {
		p.isAccessLog = true
	}

	return p
}

func parseTimestamp(ts string) time.Time {
	if ts == "" {
		return time.Time{}
	}
	if len(ts) > 2 && ts[0] == '[' && ts[len(ts)-1] == ']' {
		ts = ts[1:len(ts)-1]
	}

	if l, ok := lastLayout.Load().(string); ok {
		if t, err := time.Parse(l, ts); err == nil {
			return t
		}
	}

	for _, l := range timestampLayouts {
		if t, err := time.Parse(l, ts); err == nil {
			lastLayout.Store(l)
			return t
		}
	}
	return time.Time{}
}

func parseHourBucket(ts string) string {
	if len(ts) >= 13 {
		return ts[:13]
	}

	t := parseTimestamp(ts)
	if t.IsZero() {
		return ""
	}
	return t.Format("2006-01-02T15")
}

func isASCIIString(s string) bool {
	for i := 0; i < len(s); i++ {
		if s[i] > 127 {
			return false
		}
	}
	return true
}

func lowerASCIIByte(c byte) byte {
	if c >= 'A' && c <= 'Z' {
		return c + ('a' - 'A')
	}
	return c
}

func containsFoldASCIIString(haystack, needleLower string) bool {
	nLen := len(needleLower)
	if nLen == 0 {
		return true
	}
	hLen := len(haystack)
	if hLen < nLen {
		return false
	}

	first := needleLower[0]
	firstUpper := first
	hasBoth := first >= 'a' && first <= 'z'
	if hasBoth {
		firstUpper = first - ('a' - 'A')
	}

	pos := 0
	maxPos := hLen - nLen
	for pos <= maxPos {
		region := haystack[pos : maxPos+1]
		idx := strings.IndexByte(region, first)
		if hasBoth {
			if iu := strings.IndexByte(region, firstUpper); iu != -1 && (idx == -1 || iu < idx) {
				idx = iu
			}
		}
		if idx == -1 {
			return false
		}
		pos += idx

		ok := true
		for j := 1; j < nLen; j++ {
			if lowerASCIIByte(haystack[pos+j]) != needleLower[j] {
				ok = false
				break
			}
		}
		if ok {
			return true
		}
		pos++
	}
	return false
}

func containsFoldASCIIBytes(haystack []byte, needleLower []byte) bool {
	nLen := len(needleLower)
	if nLen == 0 {
		return true
	}
	hLen := len(haystack)
	if hLen < nLen {
		return false
	}

	first := needleLower[0]
	firstUpper := first
	hasBoth := first >= 'a' && first <= 'z'
	if hasBoth {
		firstUpper = first - ('a' - 'A')
	}

	pos := 0
	maxPos := hLen - nLen
	for pos <= maxPos {
		region := haystack[pos : maxPos+1]
		idx := bytes.IndexByte(region, first)
		if hasBoth {
			if iu := bytes.IndexByte(region, firstUpper); iu != -1 && (idx == -1 || iu < idx) {
				idx = iu
			}
		}
		if idx == -1 {
			return false
		}
		pos += idx

		ok := true
		for j := 1; j < nLen; j++ {
			if lowerASCIIByte(haystack[pos+j]) != needleLower[j] {
				ok = false
				break
			}
		}
		if ok {
			return true
		}
		pos++
	}
	return false
}

func containsSearchValue(value, searchValue string, isRegex, isCaseSensitive, searchASCII bool, re *regexp.Regexp) bool {
	if searchValue == "" {
		return true
	}
	if isRegex && re != nil {
		return re.MatchString(value)
	}
	if isCaseSensitive {
		return strings.Contains(value, searchValue)
	}
	if searchASCII {
		return containsFoldASCIIString(value, searchValue)
	}
	return strings.Contains(strings.ToLower(value), searchValue)
}

func unmarshalLenientJSON(data []byte, v interface{}) error {
	err := json.Unmarshal(data, v)
	if err == nil || bytes.IndexByte(data, '\n') == -1 {
		return err
	}

	sanitized := bytes.ReplaceAll(data, []byte{'\n'}, []byte{' '})
	return json.Unmarshal(sanitized, v)
}

// findJSONStartBefore scans line[0:before] from right to left and returns
// the index of the rightmost '{' or '[' that is preceded by whitespace (or
// sits at index 0). Returns -1 if no candidate is found.
func findJSONStartBefore(line []byte, before int) int {
	for i := before - 1; i >= 0; i-- {
		c := line[i]
		if c != '{' && c != '[' {
			continue
		}
		if i == 0 {
			return i
		}
		switch line[i-1] {
		case ' ', '\t', '\n', '\r':
			return i
		}
	}
	return -1
}

func toUpperASCIIString(s string) string {
	for i := 0; i < len(s); i++ {
		if s[i] >= 'a' && s[i] <= 'z' {
			b := []byte(s)
			for ; i < len(b); i++ {
				if b[i] >= 'a' && b[i] <= 'z' {
					b[i] -= 'a' - 'A'
				}
			}
			return string(b)
		}
	}
	return s
}

// toUpperASCII returns an uppercase string from b without allocation
// when the input is already uppercase (common case for log levels).
func toUpperASCII(b []byte) string {
	for i := 0; i < len(b); i++ {
		if b[i] >= 'a' && b[i] <= 'z' {
			upper := make([]byte, len(b))
			copy(upper[:i], b[:i])
			upper[i] = b[i] - ('a' - 'A')
			for j := i + 1; j < len(b); j++ {
				if b[j] >= 'a' && b[j] <= 'z' {
					upper[j] = b[j] - ('a' - 'A')
				} else {
					upper[j] = b[j]
				}
			}
			return unsafeString(upper)
		}
	}
	return unsafeString(b)
}

func extractFastStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if len(line) < 10 || line[0] != '[' {
		return "", "", "", false
	}

	endTs := bytes.IndexByte(line, ']')
	if endTs == -1 || len(line) <= endTs+2 || line[endTs+1] != ' ' {
		return "", "", "", false
	}

	timestamp := unsafeString(line[1:endTs])
	rest := line[endTs+2:]

	dotIdx := bytes.IndexByte(rest, '.')
	if dotIdx == -1 {
		return "", "", "", false
	}

	colonIdx := bytes.IndexByte(rest[dotIdx:], ':')
	if colonIdx == -1 {
		return "", "", "", false
	}

	channel = unsafeString(rest[:dotIdx])
	level = toUpperASCII(rest[dotIdx+1 : dotIdx+colonIdx])
	hour = parseHourBucket(timestamp)

	return level, channel, hour, true
}

func (p *UniversalParser) Name() string { return "universal" }

func (p *UniversalParser) Detect(line []byte) bool { return true }

func (p *UniversalParser) IsNewEntry(line []byte) bool {
	if len(line) == 0 {
		return false
	}
	if p.isAccessLog {
		return true
	}
	return line[0] == '['
}

func (p *UniversalParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	if p.isAccessLog {
		return extractAccessStatsFields(line, "access")
	}
	if p.useFastPath {
		return extractFastStatsFields(line)
	}
	return "", "", "", false
}

func (p *UniversalParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}

	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if p.useFastPath {
		if p.tryFastPath(line, entry, full, parseTime) {
			return entry
		}
		entry.Reset()
		entry.File = filename
	}

	m := p.re.FindSubmatchIndex(line)
	if m == nil {
		entry.Level = "INFO"
		entry.Channel = "app"
		entry.Message = unsafeString(line)
		return entry
	}

	var timestamp, level, channel, message, file, context, extra string
	if p.timestampIdx >= 0 && (p.timestampIdx*2+1) < len(m) {
		start, end := m[p.timestampIdx*2], m[p.timestampIdx*2+1]
		if start >= 0 && end >= 0 {
			timestamp = unsafeString(line[start:end])
		}
	}
	if p.levelIdx >= 0 && (p.levelIdx*2+1) < len(m) {
		start, end := m[p.levelIdx*2], m[p.levelIdx*2+1]
		if start >= 0 && end >= 0 {
			level = unsafeString(line[start:end])
		}
	}
	if p.channelIdx >= 0 && (p.channelIdx*2+1) < len(m) {
		start, end := m[p.channelIdx*2], m[p.channelIdx*2+1]
		if start >= 0 && end >= 0 {
			channel = unsafeString(line[start:end])
		}
	}
	if p.messageIdx >= 0 && (p.messageIdx*2+1) < len(m) {
		start, end := m[p.messageIdx*2], m[p.messageIdx*2+1]
		if start >= 0 && end >= 0 {
			message = unsafeString(line[start:end])
		}
	}
	if p.fileIdx >= 0 && (p.fileIdx*2+1) < len(m) {
		start, end := m[p.fileIdx*2], m[p.fileIdx*2+1]
		if start >= 0 && end >= 0 {
			file = unsafeString(line[start:end])
		}
	}
	if full && p.contextIdx >= 0 && (p.contextIdx*2+1) < len(m) {
		start, end := m[p.contextIdx*2], m[p.contextIdx*2+1]
		if start >= 0 && end >= 0 {
			context = unsafeString(line[start:end])
		}
	}
	if full && p.extraIdx >= 0 && (p.extraIdx*2+1) < len(m) {
		start, end := m[p.extraIdx*2], m[p.extraIdx*2+1]
		if start >= 0 && end >= 0 {
			extra = unsafeString(line[start:end])
		}
	}

	// Access log: map request→message, status→level, build context
	if p.isAccessLog {
		if message == "" && p.requestIdx >= 0 && (p.requestIdx*2+1) < len(m) {
			start, end := m[p.requestIdx*2], m[p.requestIdx*2+1]
			if start >= 0 && end >= 0 {
				message = unsafeString(line[start:end])
			}
		}

		var statusCode int
		if p.statusIdx >= 0 && (p.statusIdx*2+1) < len(m) {
			start, end := m[p.statusIdx*2], m[p.statusIdx*2+1]
			if start >= 0 && end >= 0 {
				statusCode, _ = strconv.Atoi(unsafeString(line[start:end]))
			}
		}
		if level == "" {
			level = statusToLevel(statusCode)
		}
		if channel == "" {
			channel = "access"
		}

		var t time.Time
		if parseTime {
			t = parseTimestamp(timestamp)
		}

		entry.Timestamp = timestamp
		entry.Level = toUpperASCIIString(level)
		entry.Channel = channel
		entry.Message = message
		entry.Time = t

		if file != "" {
			entry.File = file
		}

		if full {
			ctx := make(map[string]interface{})
			if p.remoteAddrIdx >= 0 && (p.remoteAddrIdx*2+1) < len(m) {
				start, end := m[p.remoteAddrIdx*2], m[p.remoteAddrIdx*2+1]
				if start >= 0 && end >= 0 {
					ctx["ip"] = unsafeString(line[start:end])
				}
			}
			if statusCode > 0 {
				ctx["status"] = statusCode
			}
			if p.bodySizeIdx >= 0 && (p.bodySizeIdx*2+1) < len(m) {
				start, end := m[p.bodySizeIdx*2], m[p.bodySizeIdx*2+1]
				if start >= 0 && end >= 0 {
					ctx["size"] = unsafeString(line[start:end])
				}
			}
			if p.refererIdx >= 0 && (p.refererIdx*2+1) < len(m) {
				start, end := m[p.refererIdx*2], m[p.refererIdx*2+1]
				if start >= 0 && end >= 0 {
					ctx["referer"] = unsafeString(line[start:end])
				}
			}
			if p.userAgentIdx >= 0 && (p.userAgentIdx*2+1) < len(m) {
				start, end := m[p.userAgentIdx*2], m[p.userAgentIdx*2+1]
				if start >= 0 && end >= 0 {
					ctx["user_agent"] = unsafeString(line[start:end])
				}
			}
			if len(ctx) > 0 {
				entry.Context = ctx
			}
		}

		return entry
	}

	var t time.Time
	if parseTime {
		t = parseTimestamp(timestamp)
	}

	entry.Timestamp = timestamp
	entry.Level = toUpperASCIIString(level)
	entry.Channel = channel
	entry.Message = message
	entry.Time = t

	if file != "" {
		entry.File = file
	}

	if full {
		var fullContext map[string]interface{}
		if context != "" {
			_ = json.Unmarshal([]byte(context), &fullContext)
		}
		if extra != "" {
			var extraCtx map[string]interface{}
			if err := json.Unmarshal([]byte(extra), &extraCtx); err == nil {
				if fullContext == nil {
					fullContext = extraCtx
				} else {
					for k, v := range extraCtx {
						fullContext[k] = v
					}
				}
			}
		}

		if fullContext != nil {
			entry.Context = fullContext
			if sql, ok := fullContext["sql"].(string); ok {
				entry.SQL = sql
			}
			if params, ok := fullContext["params"].(map[string]interface{}); ok {
				entry.Parameters = params
			}
		}
	}

	return entry
}

func (p *UniversalParser) tryFastPath(line []byte, entry *LogEntry, full, parseTime bool) bool {
	if len(line) < 10 || line[0] != '[' {
		return false
	}
	endTs := bytes.IndexByte(line, ']')
	if endTs == -1 {
		return false
	}
	entry.Timestamp = unsafeString(line[1:endTs])

	if len(line) <= endTs+2 || line[endTs+1] != ' ' {
		return false
	}

	rest := line[endTs+2:]
	dotIdx := bytes.IndexByte(rest, '.')
	if dotIdx == -1 {
		return false
	}
	entry.Channel = unsafeString(rest[:dotIdx])

	colonIdx := bytes.IndexByte(rest[dotIdx:], ':')
	if colonIdx == -1 {
		return false
	}
	entry.Level = toUpperASCII(rest[dotIdx+1 : dotIdx+colonIdx])

	messagePart := rest[dotIdx+colonIdx+1:]
	if len(messagePart) > 0 && messagePart[0] == ' ' {
		messagePart = messagePart[1:]
	}

	entry.Message = unsafeString(bytes.TrimSpace(messagePart))
	if full {
		current := messagePart
		var combinedContext map[string]interface{}

		for i := 0; i < 2; i++ {
			trimmed := bytes.TrimSpace(current)
			if len(trimmed) == 0 {
				break
			}

			// Scan candidates right-to-left; the first success is the answer.
			// json.Unmarshal rejects trailing content, so only the outermost
			// complete block succeeds — a suffix of valid JSON is never itself
			// valid JSON, guaranteeing at most one success.
			var bestMap map[string]interface{}
			bestIsMap := false
			bestIdx := -1

			searchBefore := len(trimmed)
			for attempts := 0; searchBefore > 0 && attempts < 32; attempts++ {
				idx := findJSONStartBefore(trimmed, searchBefore)
				if idx == -1 {
					break
				}

				potentialJSON := trimmed[idx:]
				var m map[string]interface{}
				if err := unmarshalLenientJSON(potentialJSON, &m); err == nil {
					bestIdx = idx
					bestMap = m
					bestIsMap = true
					break
				}
				var a []interface{}
				if err := unmarshalLenientJSON(potentialJSON, &a); err == nil {
					bestIdx = idx
					bestIsMap = false
					break
				}
				searchBefore = idx
			}

			if bestIdx == -1 {
				break
			}
			if bestIsMap {
				if combinedContext == nil {
					combinedContext = bestMap
				} else {
					for k, v := range bestMap {
						combinedContext[k] = v
					}
				}
			}
			current = trimmed[:bestIdx]
		}

		entry.Message = unsafeString(bytes.TrimSpace(current))
		entry.Context = combinedContext
		if combinedContext != nil {
			if sql, ok := combinedContext["sql"].(string); ok {
				entry.SQL = sql
			}
			if params, ok := combinedContext["params"].(map[string]interface{}); ok {
				entry.Parameters = params
			}
		}
	}

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return true
}