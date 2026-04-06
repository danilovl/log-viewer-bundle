package main

import (
	"regexp"
	"testing"
)

const benchPattern = `^\[(?P<timestamp>.*?)\] (?P<channel>.*?)\.(?P<level>.*?): (?P<message>.*?)(?: (?P<context>\{.*?\}|\[.*?\]))?(?: (?P<extra>\{.*?\}|\[.*?\]))?$`

var (
	benchParser = NewUniversalParser(regexp.MustCompile(benchPattern))
	benchLine   = []byte(`[2026-03-29T09:44:14.945778+00:00] app.INFO: Request started {"route":"dashboard","user":123} {"request_id":"abc-123"}`)
	benchLineSQL = []byte(`[2026-03-29T09:44:16.945778+00:00] doctrine.DEBUG: Executing query {"sql":"SELECT * FROM table WHERE id = ?","params":{"1":123}} []`)
)

func BenchmarkParseFastNoTime(b *testing.B) {
	b.ReportAllocs()

	for i := 0; i < b.N; i++ {
		entry := benchParser.Parse(benchLine, "monolog.log", false, false)
		if entry == nil {
			b.Fatal("entry is nil")
		}
		PutEntry(entry)
	}
}

func BenchmarkParseFullNoTime(b *testing.B) {
	b.ReportAllocs()

	for i := 0; i < b.N; i++ {
		entry := benchParser.Parse(benchLineSQL, "monolog.log", true, false)
		if entry == nil {
			b.Fatal("entry is nil")
		}
		PutEntry(entry)
	}
}

func BenchmarkContainsSearchValueASCII(b *testing.B) {
	b.ReportAllocs()
	text := "Executing query SELECT * FROM table WHERE id = 123"
	needle := "select * from table"

	for i := 0; i < b.N; i++ {
		if !containsSearchValue(text, needle, true) {
			b.Fatal("needle was not found")
		}
	}
}

func BenchmarkParseHourBucket(b *testing.B) {
	b.ReportAllocs()
	ts := "2026-03-29T09:44:14.945778+00:00"

	for i := 0; i < b.N; i++ {
		hour := parseHourBucket(ts)
		if hour == "" {
			b.Fatal("hour is empty")
		}
	}
}
