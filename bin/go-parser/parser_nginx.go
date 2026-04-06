package main

// ---------------------------------------------------------------------------
// Month lookup table — shared by access log parsers and syslog
// ---------------------------------------------------------------------------

var monthToNum = [...]string{
	'a' + 'n': "01", // Jan
	'e' + 'b': "02", // Feb
	'a' + 'r': "03", // Mar
	'p' + 'r': "04", // Apr
	'a' + 'y': "05", // May
	'u' + 'n': "06", // Jun
	'u' + 'l': "07", // Jul
	'u' + 'g': "08", // Aug
	'e' + 'p': "09", // Sep
	'c' + 't': "10", // Oct
	'o' + 'v': "11", // Nov
	'e' + 'c': "12", // Dec
}

func monthKey(b []byte) int {
	if len(b) < 3 {
		return 0
	}
	return int(b[1]) + int(b[2])
}

func lookupMonth(b []byte) string {
	k := monthKey(b)
	if k >= 0 && k < len(monthToNum) {
		return monthToNum[k]
	}
	return ""
}

func statusToLevel(status int) string {
	switch {
	case status >= 500:
		return "ERROR"
	case status >= 400:
		return "WARNING"
	default:
		return "INFO"
	}
}

// ---------------------------------------------------------------------------
// NginxAccessParser — Combined Log Format
// 93.180.71.3 - - [17/May/2015:08:05:32 +0000] "GET /path HTTP/1.1" 304 0 "-" "ua"
// ---------------------------------------------------------------------------

type NginxAccessParser struct{}

func NewNginxAccessParser() *NginxAccessParser { return &NginxAccessParser{} }

func (p *NginxAccessParser) Name() string { return "nginx_access" }

func (p *NginxAccessParser) Detect(line []byte) bool { return detectAccessLog(line) }

func (p *NginxAccessParser) IsNewEntry(line []byte) bool { return len(line) > 0 }

func (p *NginxAccessParser) ExtractStatsFields(line []byte) (level, channel, hour string, ok bool) {
	return extractAccessStatsFields(line, "access")
}

func (p *NginxAccessParser) Parse(line []byte, filename string, full, parseTime bool) *LogEntry {
	if len(line) == 0 {
		return nil
	}
	entry := entryPool.Get().(*LogEntry)
	entry.File = filename
	parseAccessLogLine(line, entry, "access", full, parseTime)
	return entry
}

