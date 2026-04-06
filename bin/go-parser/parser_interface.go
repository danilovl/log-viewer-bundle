package main

// LogParser defines the interface for log format parsers.
type LogParser interface {
	// Name returns the parser identifier.
	Name() string
	// Detect returns true if the sample line matches this parser's format.
	Detect(line []byte) bool
	// IsNewEntry returns true if the line starts a new log entry (for multiline joining).
	IsNewEntry(line []byte) bool
	// Parse parses a log entry into a LogEntry.
	Parse(line []byte, filename string, full, parseTime bool) *LogEntry
	// ExtractStatsFields extracts level, channel, hour for fast stats with minimal allocation.
	ExtractStatsFields(line []byte) (level, channel, hour string, ok bool)
}

var parserRegistry = map[string]func() LogParser{
	"monolog":       func() LogParser { return NewMonologParser() },
	"nginx_access":  func() LogParser { return NewNginxAccessParser() },
	"nginx_error":   func() LogParser { return NewNginxErrorParser() },
	"apache_access": func() LogParser { return NewApacheAccessParser() },
	"syslog":        func() LogParser { return NewSyslogParser() },
	"auth":          func() LogParser { return NewAuthLogParser() },
	"kern":          func() LogParser { return NewKernLogParser() },
	"php_fpm":       func() LogParser { return NewPhpFpmParser() },
	"php_error":     func() LogParser { return NewPhpErrorParser() },
	"supervisord":   func() LogParser { return NewSupervisordParser() },
	"mysql":         func() LogParser { return NewMysqlParser() },
	"json":          func() LogParser { return NewJSONLogParser() },
}

// detectParserFromLine tries each registered parser on a sample line.
// Order: most specific first to avoid false positives.
func detectParserFromLine(sample []byte) LogParser {
	order := []string{
		"nginx_error",   // YYYY/MM/DD HH:MM:SS [level] — very specific
		"supervisord",   // YYYY-MM-DD HH:MM:SS,mmm — comma before ms is unique
		"mysql",         // YYYY-MM-DDTHH:MM:SS.ffffffZ TID [Level] — Z suffix + brackets
		"php_error",     // [timestamp] PHP Level: — "PHP " prefix after bracket
		"monolog",       // [timestamp] channel.LEVEL: — dot+colon after bracket
		"nginx_access",  // IP - - [DD/Mon/YYYY:HH:MM:SS] "request" — access log format
		"auth",          // RFC3339 hostname sudo/sshd/pam_
		"kern",          // RFC3339 hostname kernel:
		"php_fpm",       // RFC3339 hostname php/fpm
		"syslog",        // Mon DD HH:MM:SS — BSD syslog, least specific timestamp
		"json",          // {" — JSON, last because it's the most permissive
	}
	for _, name := range order {
		p := parserRegistry[name]()
		if p.Detect(sample) {
			return p
		}
	}
	return nil
}
