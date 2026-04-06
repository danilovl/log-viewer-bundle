package main

import "bytes"

// MonologParser handles PHP Monolog/Symfony/Laravel log format:
// [2026-03-29T09:44:14.945778+00:00] app.INFO: Request started {"context"} {"extra"}
type MonologParser struct{}

func NewMonologParser() *MonologParser { return &MonologParser{} }

func (p *MonologParser) Name() string { return "monolog" }

func (p *MonologParser) Detect(line []byte) bool {
	if len(line) < 15 || line[0] != '[' {
		return false
	}
	endTs := bytes.IndexByte(line, ']')
	if endTs < 10 || len(line) <= endTs+2 || line[endTs+1] != ' ' {
		return false
	}
	rest := line[endTs+2:]
	dotIdx := bytes.IndexByte(rest, '.')
	if dotIdx == -1 || dotIdx == 0 {
		return false
	}
	colonIdx := bytes.IndexByte(rest[dotIdx:], ':')
	return colonIdx > 1
}

func (p *MonologParser) IsNewEntry(line []byte) bool {
	return len(line) > 0 && line[0] == '['
}

func (p *MonologParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	return extractFastStatsFields(line)
}

func (p *MonologParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename

	if parseMonologLine(line, entry, full, parseTime) {
		return entry
	}

	entry.Reset()
	entry.File = filename
	entry.Level = "INFO"
	entry.Channel = "app"
	entry.Message = unsafeString(line)
	return entry
}

// parseMonologLine is the shared fast-path byte-level parser for monolog format.
// Used by both MonologParser and UniversalParser's fast path.
func parseMonologLine(line []byte, entry *LogEntry, full, parseTime bool) bool {
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
