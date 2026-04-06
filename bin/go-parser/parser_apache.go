package main

// ApacheAccessParser — Apache Combined/Common Log Format.
// Identical to nginx access format, differs only in channel name.
// 127.0.0.1 - - [29/Mar/2026:11:00:00 +0000] "GET / HTTP/1.1" 200 225

type ApacheAccessParser struct{}

func NewApacheAccessParser() *ApacheAccessParser { return &ApacheAccessParser{} }

func (p *ApacheAccessParser) Name() string { return "apache_access" }

func (p *ApacheAccessParser) Detect(line []byte) bool { return detectAccessLog(line) }

func (p *ApacheAccessParser) IsNewEntry(line []byte) bool { return len(line) > 0 }

func (p *ApacheAccessParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	return extractAccessStatsFields(line, "apache")
}

func (p *ApacheAccessParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	parseAccessLogLine(line, entry, "apache", full, parseTime)
	return entry
}
