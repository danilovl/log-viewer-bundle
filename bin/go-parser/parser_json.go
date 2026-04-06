package main

import (
	"bytes"
	"encoding/json"
	"strings"
)

// JSONLogParser handles structured JSON log lines:
// {"timestamp":"...","level":"...","message":"...","channel":"..."}
type JSONLogParser struct{}

func NewJSONLogParser() *JSONLogParser { return &JSONLogParser{} }

func (p *JSONLogParser) Name() string { return "json" }

func (p *JSONLogParser) Detect(line []byte) bool {
	line = bytes.TrimSpace(line)
	if len(line) < 2 || line[0] != '{' || line[len(line)-1] != '}' {
		return false
	}

	return bytes.Contains(line, []byte(`"level"`)) ||
		bytes.Contains(line, []byte(`"msg"`)) ||
		bytes.Contains(line, []byte(`"message"`))
}

func (p *JSONLogParser) IsNewEntry(line []byte) bool {
	line = bytes.TrimSpace(line)
	return len(line) > 0 && line[0] == '{'
}

func (p *JSONLogParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	line = bytes.TrimSpace(line)
	if len(line) < 2 || line[0] != '{' {
		return "", "", "", false
	}

	level = extractJSONStringValue(line, []byte(`"level"`))
	if level == "" {
		level = extractJSONStringValue(line, []byte(`"severity"`))
	}
	if level == "" {
		level = "INFO"
	} else {
		level = toUpperASCIIString(level)
	}

	channel = extractJSONStringValue(line, []byte(`"channel"`))
	if channel == "" {
		channel = extractJSONStringValue(line, []byte(`"logger"`))
	}
	if channel == "" {
		channel = "app"
	}

	ts := extractJSONStringValue(line, []byte(`"timestamp"`))
	if ts == "" {
		ts = extractJSONStringValue(line, []byte(`"time"`))
	}
	if ts == "" {
		ts = extractJSONStringValue(line, []byte(`"datetime"`))
	}
	if ts == "" {
		ts = extractJSONStringValue(line, []byte(`"@timestamp"`))
	}
	if ts != "" {
		hour = parseHourBucket(ts)
	}

	return level, channel, hour, true
}

// extractJSONStringValue does a fast byte scan to find "key":"value" and returns the value.
func extractJSONStringValue(data []byte, key []byte) string {
	idx := bytes.Index(data, key)
	if idx == -1 {
		return ""
	}
	rest := data[idx+len(key):]

	for len(rest) > 0 && (rest[0] == ' ' || rest[0] == '\t' || rest[0] == ':') {
		rest = rest[1:]
	}
	if len(rest) == 0 || rest[0] != '"' {
		return ""
	}
	rest = rest[1:]
	end := bytes.IndexByte(rest, '"')
	if end == -1 {
		return ""
	}
	return unsafeString(rest[:end])
}

func (p *JSONLogParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	line = bytes.TrimSpace(line)
	if len(line) < 2 || line[0] != '{' {
		entry.Level = "INFO"
		entry.Channel = "app"
		entry.Message = unsafeString(line)
		return entry
	}

	var raw map[string]interface{}
	if err := json.Unmarshal(line, &raw); err != nil {
		entry.Level = "INFO"
		entry.Channel = "app"
		entry.Message = unsafeString(line)
		return entry
	}

	entry.Timestamp = jsonStringField(raw, "timestamp", "time", "datetime", "@timestamp")
	entry.Level = toUpperASCIIString(jsonStringField(raw, "level", "severity"))
	if entry.Level == "" {
		entry.Level = "INFO"
	}
	entry.Channel = jsonStringField(raw, "channel", "logger")
	if entry.Channel == "" {
		entry.Channel = "app"
	}
	entry.Message = jsonStringField(raw, "message", "msg")

	if full {
		ctx := make(map[string]interface{})
		for k, v := range raw {
			switch strings.ToLower(k) {
			case "timestamp", "time", "datetime", "@timestamp",
				"level", "severity", "channel", "logger",
				"message", "msg":
				continue
			}
			ctx[k] = v
		}
		if len(ctx) > 0 {
			entry.Context = ctx
			if sql, ok := ctx["sql"].(string); ok {
				entry.SQL = sql
			}
			if params, ok := ctx["params"].(map[string]interface{}); ok {
				entry.Parameters = params
			}
		}
	}

	if parseTime {
		entry.Time = parseTimestamp(entry.Timestamp)
	}
	return entry
}

func jsonStringField(m map[string]interface{}, keys ...string) string {
	for _, k := range keys {
		if v, ok := m[k]; ok {
			if s, ok := v.(string); ok {
				return s
			}
		}
	}
	return ""
}
