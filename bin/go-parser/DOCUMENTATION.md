# Go Log Parser — Documentation

High-performance Go tool for parsing, searching, and collecting statistics from log files containing millions of lines.

---

## Table of Contents

- [CLI Parameters](#cli-parameters)
- [Modes](#modes)
- [Output Format](#output-format)
- [Architecture](#architecture)
- [File Descriptions](#file-descriptions)
  - [main.go](#maingo)
  - [parser_interface.go](#parser_interfacego)
  - [parser.go](#parsergo)
  - [parser_monolog.go](#parser_monologgo)
  - [parser_nginx.go](#parser_nginxgo)
  - [parser_nginx_error.go](#parser_nginx_errorgo)
  - [parser_access_common.go](#parser_access_commongo)
  - [parser_apache.go](#parser_apachego)
  - [parser_syslog.go](#parser_sysloggo)
  - [parser_rfc3339_syslog.go](#parser_rfc3339_sysloggo)
  - [parser_supervisord.go](#parser_supervisordgo)
  - [parser_mysql.go](#parser_mysqlgo)
  - [parser_json.go](#parser_jsongo)
  - [parser_php_error.go](#parser_php_errorgo)
  - [logs.go](#logsgo)
  - [stats.go](#statsgo)
  - [mmap.go](#mmapgo)
  - [reader.go](#readergo)
  - [gz.go](#gzgo)
  - [perf_test.go](#perf_testgo)
- [Gzip Support](#gzip-support)
- [Supported Log Formats](#supported-log-formats)
- [Parser Selection](#parser-selection)
- [Performance Techniques](#performance-techniques)

---

## CLI Parameters

| Parameter     | Type   | Default | Description                                                              |
|---------------|--------|---------|--------------------------------------------------------------------------|
| `--file`      | string | *required* | Path to the log file (supports `.gz` compressed files)           |
| `--parser`    | string | ""      | Parser type (auto-detect if omitted)                                     |
| `--pattern`   | string | ""      | Regexp pattern with named groups for `UniversalParser`                   |
| `--limit`     | int    | 50      | Maximum number of entries to return (mode `logs`)                        |
| `--offset`    | int    | 0       | Number of entries to skip (mode `logs`)                                  |
| `--cursor`    | string | ""      | Timestamp cursor for pagination (RFC3339Nano)                            |
| `--level`     | string | ""      | Filter by log level (`ERROR`, `WARNING`, `INFO`, `DEBUG`)                |
| `--channel`   | string | ""      | Filter by channel name                                                   |
| `--search`    | string | ""      | Search string in message or SQL fields                                   |
| `--sort`      | string | "desc"  | Sort direction: `asc` or `desc`                                          |
| `--mode`      | string | "logs"  | Mode: `logs`, `stat_dashboard` (`stats`), `stat_log` (`stat_filter`)     |

### Parser priority

1. `--parser` — use a specific built-in parser by name
2. `--pattern` — use `UniversalParser` with the given regex
3. Neither — auto-detect format from the first non-empty lines of the file

### Available `--parser` values

`monolog`, `nginx_access`, `nginx_error`, `apache_access`, `syslog`, `auth`, `kern`, `php_fpm`, `php_error`, `supervisord`, `mysql`, `json`

---

## Modes

### `logs` (default)

Returns parsed log entries as newline-delimited JSON objects to stdout. Supports filtering, search, cursor-based pagination, offset/limit, and ascending/descending sort.

### `stat_dashboard` (alias: `stats`)

Scans the entire file and returns aggregated statistics as a single JSON object. No filters applied. Uses data-parallel processing.

### `stat_log` (alias: `stat_filter`)

Same as `stat_dashboard` but applies `--level`, `--channel`, and `--search` filters before counting.

---

## Output Format

### Log entry (mode `logs`)

Each line is a JSON object:

```json
{
  "timestamp": "2026-03-29T09:44:14.945778+00:00",
  "level": "ERROR",
  "channel": "app",
  "message": "Something went wrong",
  "sql": "SELECT * FROM users",
  "parameters": {"1": 123},
  "exceptionClass": "",
  "file": "monolog.log",
  "context": {"key": "value"}
}
```

| Field            | Type   | Description                                        |
|------------------|--------|----------------------------------------------------|
| `timestamp`      | string | Raw timestamp from the log line                    |
| `level`          | string | Normalized uppercase level: `ERROR`, `WARNING`, `INFO`, `DEBUG` |
| `channel`        | string | Log channel/source (e.g. `app`, `access`, `nginx`) |
| `message`        | string | Log message content                                |
| `sql`            | string | SQL query if found in context (omitted if empty)   |
| `parameters`     | object | SQL parameters if found in context (omitted if empty) |
| `exceptionClass` | string | Exception class if present (omitted if empty)      |
| `file`           | string | Base name of the log file                          |
| `context`        | object | Additional context/metadata (omitted if empty)     |

### Statistics (modes `stat_dashboard`, `stat_log`)

Single JSON object:

```json
{
  "updated_at": "2026-03-29T12:00:00Z",
  "size": 1048576,
  "total": 50000,
  "levels": {"ERROR": 150, "WARNING": 800, "INFO": 49050},
  "channels": {"app": 30000, "access": 20000},
  "timeline": {"2026-03-29T09": 5000, "2026-03-29T10": 4500}
}
```

| Field        | Type   | Description                               |
|--------------|--------|-------------------------------------------|
| `updated_at` | string | File modification time (RFC3339)          |
| `size`       | int    | File size in bytes                        |
| `total`      | int    | Total number of matched entries           |
| `levels`     | object | Count of entries per level                |
| `channels`   | object | Count of entries per channel              |
| `timeline`   | object | Count of entries per hour bucket (`YYYY-MM-DDTHH`) |

---

## Architecture

```
main.go
  |
  +-- parser_interface.go        LogParser interface + registry + auto-detection
  |
  +-- gz.go                      Gzip support: isGzipFile / readGzipFull / stripGzExt
  |
  +-- logs.go                    runLogs: paginated log retrieval
  |     |-- mmap.go              Mmap / Munmap / MadviseSequential / unsafeString
  |     +-- reader.go            forwardLineReader / reverseLineReader (fallback)
  |
  +-- stats.go                   runStatsDashboard / runStatsLog: parallel stats
  |     +-- mmap.go
  |
  +-- parser.go                  LogEntry, UniversalParser, shared utilities
        |
        +-- parser_monolog.go           MonologParser
        +-- parser_nginx.go             NginxAccessParser, shared month table
        +-- parser_nginx_error.go      NginxErrorParser
        +-- parser_access_common.go     Shared access log helpers, month table
        +-- parser_apache.go            ApacheAccessParser
        +-- parser_syslog.go            SyslogParser, syslogLevelFromMessage
        +-- parser_rfc3339_syslog.go    AuthLogParser, KernLogParser, PhpFpmParser
        +-- parser_supervisord.go       SupervisordParser
        +-- parser_mysql.go             MysqlParser
        +-- parser_json.go              JSONLogParser
        +-- parser_php_error.go         PhpErrorParser

perf_test.go                     Benchmarks
```

---

## File Descriptions

### main.go

Entry point. Parses CLI flags, resolves which parser to use (explicit `--parser`, `--pattern`, or auto-detect), then dispatches to `runStatsDashboard`, `runStatsLog`, or `runLogs`.

**Key functions:**
- `main()` — flag parsing, parser resolution, mode dispatch
- `autoDetectParser(filePath) LogParser` — reads up to 10 lines (decompresses `.gz` transparently), calls `detectParserFromLine`

---

### parser_interface.go

Defines the `LogParser` interface that all parsers implement, the parser registry, and auto-detection logic.

**Interface `LogParser`:**

| Method               | Signature                                                            | Description                                  |
|----------------------|----------------------------------------------------------------------|----------------------------------------------|
| `Name`               | `() string`                                                          | Parser identifier                            |
| `Detect`             | `(line []byte) bool`                                                 | Returns true if the line matches this format |
| `IsNewEntry`         | `(line []byte) bool`                                                 | Returns true if line starts a new log entry  |
| `Parse`              | `(line []byte, filename string, full, parseTime bool) *LogEntry`     | Full parse into LogEntry                     |
| `ExtractStatsFields` | `(line []byte) (level, channel, hour string, ok bool)`               | Fast stats extraction (minimal allocation)   |

**`parserRegistry`** — maps parser names to factory functions.

**`detectParserFromLine`** — tries parsers in order (most specific first):
`nginx_error` > `supervisord` > `mysql` > `php_error` > `monolog` > `nginx_access` > `auth` > `kern` > `php_fpm` > `syslog` > `json`

---

### parser.go

Core shared infrastructure.

**`LogEntry` struct** — the canonical parsed log record used by all parsers and output modes.

**`UniversalParser`** — regex-based generic parser with named capture groups (`timestamp`, `level`, `channel`, `message`, `file`, `context`, `extra`). Supports a monolog-format fast path when the pattern matches `^\[...] channel.LEVEL:`.

**Key shared functions:**

| Function                  | Description                                                    |
|---------------------------|----------------------------------------------------------------|
| `NewUniversalParser(re)`  | Constructs parser from a compiled regexp                       |
| `PutEntry(e)`             | Returns LogEntry to sync.Pool                                  |
| `parseTimestamp(ts)`      | Tries cached layout, then 8 known formats                      |
| `parseHourBucket(ts)`     | Returns `YYYY-MM-DDTHH` from timestamp string                 |
| `extractFastStatsFields`  | Fast byte-scan for monolog-style lines                         |
| `containsFoldASCIIBytes`  | Case-insensitive byte-slice substring search (no allocation)   |
| `containsFoldASCIIString` | Case-insensitive string substring search (no allocation)       |
| `containsSearchValue`     | Dispatches to ASCII or Unicode fold search                     |
| `toUpperASCII(b)`         | Zero-alloc uppercase from bytes (when already uppercase)       |
| `toUpperASCIIString(s)`   | Zero-alloc uppercase from string                               |
| `unmarshalLenientJSON`    | JSON unmarshal that tolerates embedded newlines                |
| `findJSONStartBefore`     | Scans right-to-left for JSON block start                       |

**Supported timestamp layouts:**

| Layout                               | Example                              |
|---------------------------------------|--------------------------------------|
| `time.RFC3339Nano`                    | `2006-01-02T15:04:05.999999999Z07:00` |
| `2006-01-02T15:04:05.000000Z07:00`   | `2026-03-29T09:44:14.945778+00:00`   |
| `2006-01-02 15:04:05`                | `2026-03-29 09:44:14`                |
| `2006-01-02 15:04:05.000000`         | `2026-03-29 09:44:14.945778`         |
| `time.RFC3339`                        | `2006-01-02T15:04:05Z07:00`         |
| `02/Jan/2006:15:04:05 -0700`         | `29/Mar/2026:10:00:00 +0000`        |
| `2006-01-02T15:04:05.000000Z`        | `2020-08-06T14:25:02.835618Z`       |
| `2015/01/02 15:04:05`                | `2015/06/11 09:44:14`               |

---

### parser_monolog.go

Parses PHP Monolog/Symfony/Laravel log format.

**Format:** `[2026-03-29T09:44:14.945778+00:00] app.INFO: Request started {"context"} {"extra"}`

**Detection:** Line starts with `[`, has `]` closing bracket, then `channel.LEVEL:` pattern.

**`IsNewEntry`:** Line starts with `[`.

**Channel:** Extracted from text between `] ` and `.` (e.g. `app`, `doctrine`, `request`).

**Level:** Text between `.` and `:` (e.g. `INFO`, `ERROR`, `DEBUG`), uppercased.

**Context:** In `full` mode, scans up to 2 JSON blocks from right-to-left, merges them into context map. Extracts `sql` and `params` fields.

**Shared function `parseMonologLine`** — reusable fast-path parser, also used internally by `UniversalParser.tryFastPath`.

---

### parser_nginx.go

Contains `NginxAccessParser`, plus shared month lookup table (`monthToNum`, `lookupMonth`, `monthKey`) and `statusToLevel`.

#### NginxAccessParser

**Format:** `93.180.71.3 - - [17/May/2015:08:05:32 +0000] "GET /path HTTP/1.1" 304 0 "-" "ua"`

**Detection:** Delegates to `detectAccessLog` (shared).

**`IsNewEntry`:** Every non-empty line is a new entry.

**Channel:** `"access"`.

**Level:** Derived from HTTP status code via `statusToLevel` (>=500: ERROR, >=400: WARNING, else INFO).

**Context (full mode):** `{"ip": "...", "status": 200, "size": "1234"}`.

---

### parser_nginx_error.go

Nginx error log parser, extracted into its own file.

**Format:** `2015/06/11 09:44:14 [error] 1234#5678: *9 message text`

**Detection:** `YYYY/MM/DD HH:MM:SS [` at fixed byte positions.

**`IsNewEntry`:** Checks `line[4]=='/'`, `line[7]=='/'`, `line[10]==' '`.

**Channel:** `"nginx"`.

**Level:** From `[level]` bracket (e.g. `error` -> `ERROR`).

**Message:** Text after `] `, with optional `PID#TID: *CID ` prefix stripped.

---

### parser_access_common.go

Shared parsing logic for Combined/Common Log Format, used by both `NginxAccessParser` and `ApacheAccessParser`.

**Functions:**

| Function                 | Description                                                     |
|--------------------------|-----------------------------------------------------------------|
| `detectAccessLog`        | Validates `[DD/Mon/YYYY:HH:MM:SS]` pattern + quoted request    |
| `extractAccessStatsFields` | Fast hour bucket + status-to-level extraction                |
| `parseAccessLogLine`     | Full parse: timestamp, IP, request, status, size, context       |

**Month lookup table** (`monthToNum`): Array indexed by `b[1]+b[2]` for zero-allocation month name-to-number conversion. Shared by access log parsers and `SyslogParser`.

---

### parser_apache.go

Apache Combined/Common Log Format parser. Functionally identical to `NginxAccessParser` with channel `"apache"` instead of `"access"`.

**Format:** `127.0.0.1 - - [29/Mar/2026:11:00:00 +0000] "GET / HTTP/1.1" 200 225`

All methods delegate to shared helpers in `parser_access_common.go`.

---

### parser_syslog.go

BSD syslog format parser.

**Format:** `Oct 11 22:14:15 host service[123]: important message`

**Detection:** 3-letter month abbreviation + space + day + space + `HH:MM:SS`.

**`IsNewEntry`:** Same byte-position checks.

**Channel:** Application name from `app[PID]:` or `app:` (PID stripped).

**Level:** Derived from message keywords via `syslogLevelFromMessage`:
- `error`, `fatal`, `panic`, `crit` -> `ERROR`
- `warn` -> `WARNING`
- `debug` -> `DEBUG`
- default -> `INFO`

**Hour bucket:** `0000-MM-DDTHH` (year unknown in BSD syslog format).

**Shared helpers:** `isUpperAlpha`, `isLowerAlpha`, `isDigit`, `syslogLevelFromMessage` (also used by RFC3339 syslog parsers).

---

### parser_rfc3339_syslog.go

Three parsers sharing the same RFC3339 syslog format: `AuthLogParser`, `KernLogParser`, `PhpFpmParser`.

**Format:** `2026-03-29T00:05:43.791811+01:00 HOSTNAME APP[PID]: message`

**Shared helpers:**

| Function                    | Description                                       |
|-----------------------------|---------------------------------------------------|
| `detectRFC3339Syslog`       | Checks `YYYY-MM-DDTHH:MM:SS` at fixed positions  |
| `isNewRFC3339Entry`         | Lightweight check: `-` at 4,7 and `T` at 10      |
| `extractRFC3339HourBucket`  | Returns first 13 bytes as hour bucket             |
| `parseRFC3339SyslogFields`  | Splits into timestamp, hostname, app, message     |

**Parser differences:**

| Parser          | Name       | Detect extra check                         | Default channel |
|-----------------|------------|--------------------------------------------|-----------------|
| `AuthLogParser` | `auth`     | Contains `sudo`, `sshd`, `pam_`, `login`, or `auth` | `"auth"`  |
| `KernLogParser` | `kern`     | Contains `kernel:`                         | `"kernel"`      |
| `PhpFpmParser`  | `php_fpm`  | Contains `php` or `fpm`                    | `"php-fpm"`     |

**Level:** All use `syslogLevelFromMessage` from `parser_syslog.go`.

---

### parser_supervisord.go

Supervisord log format parser.

**Format:** `2026-03-27 21:04:56,966 CRIT Supervisor is running as root.`

**Detection:** `YYYY-MM-DD HH:MM:SS,` — comma at position 19 is the unique marker.

**Channel:** Always `"supervisord"`.

**Level:** Normalized via `normalizeSupervisordLevel`:
- `CRIT`, `CRITICAL`, `FATAL` -> `ERROR`
- `WARN` -> `WARNING`
- `INFO`, `DEBUG`, `ERROR`, `WARNING` -> pass through
- default -> `INFO`

**Timestamp:** 23 characters (`YYYY-MM-DD HH:MM:SS,mmm`). For `parseTime`, comma is replaced with dot before parsing.

---

### parser_mysql.go

MySQL error/general log format parser.

**Format:** `2020-08-06T14:25:02.835618Z 0 [Note] [MY-012487] [InnoDB] DDL log recovery : begin`

**Detection:** RFC3339-like timestamp with `T` at position 10, plus `[Level]` bracket after position 20.

**Channel:** Default `"mysql"`. In `full` mode, extracted from `[Category]` bracket (e.g. `InnoDB`, `Server`).

**Level:** Normalized via `normalizeMysqlLevel`:
- `Note`, `System` -> `INFO`
- `Warning` -> `WARNING`
- `Error` -> `ERROR`
- others -> uppercased

**Structure:** Timestamp -> Thread ID -> `[Level]` -> `[MY-XXXXXX]` (error code) -> `[Category]` -> Message.

---

### parser_json.go

Structured JSON log line parser.

**Format:** `{"timestamp":"...","level":"...","message":"...","channel":"..."}`

**Detection:** Line is `{...}` and contains `"level"`, `"msg"`, or `"message"`.

**`IsNewEntry`:** Line starts with `{` (after trim).

**Field key aliases:**

| Field     | Accepted keys                              |
|-----------|--------------------------------------------|
| Timestamp | `timestamp`, `time`, `datetime`, `@timestamp` |
| Level     | `level`, `severity`                        |
| Channel   | `channel`, `logger`                        |
| Message   | `message`, `msg`                           |

**ExtractStatsFields:** Uses fast byte scanning (`extractJSONStringValue`) without full JSON parse.

**Parse:** Full `json.Unmarshal`. Known keys are mapped to LogEntry fields; remaining keys become context. Extracts `sql` and `params` from context.

---

### parser_php_error.go

PHP error log format parser.

**Format:** `[2026-03-29T09:44:14.945778+00:00] PHP Fatal error:  message in /path on line N`

**Detection:** Line starts with `[timestamp]` followed by `PHP `.

**`IsNewEntry`:** Line starts with `[`.

**Channel:** Always `"php"`.

**Level:** Derived from PHP error type:
- `Fatal error`, `Parse error` -> `ERROR`
- `Warning` -> `WARNING`
- `Notice`, `Deprecated` -> `INFO`
- `Strict` -> `DEBUG`
- default -> `ERROR`

---

### logs.go

Implements `runLogs` — the main log retrieval mode.

**Key functions:**

| Function              | Description                                                      |
|-----------------------|------------------------------------------------------------------|
| `runLogs`             | Main entry: opens file, applies filters/cursor/pagination, outputs JSON |
| `binarySearchMmap`    | O(log n) binary search for cursor-based pagination on mmap data  |
| `findEntryAfter`      | Scans forward to find next entry boundary                        |
| `quickTimestamp`       | Heuristic timestamp extraction (monolog/nginx/syslog)            |
| `emitMmapLinesAsc`    | Forward iteration over mmap data, assembles multiline entries    |
| `emitMmapLinesDesc`   | Reverse iteration over mmap data, assembles multiline entries    |

**Processing pipeline:**
1. Open file: if `.gz` — decompress fully into memory via `readGzipFull`; otherwise try mmap (fallback to `reader.go`)
2. If cursor set, binary search to narrow scan range
3. Iterate entries (asc or desc)
4. Apply byte-level pre-filters (level, channel, search) — fast rejection
5. Full parse via `parser.Parse(line, filename, true, needTime)`
6. Apply field-level filters (exact level/channel match, search in message/SQL)
7. Apply cursor time comparison
8. Skip offset entries, collect up to limit
9. Encode each entry as JSON line to buffered stdout

---

### stats.go

Implements `runStatsDashboard` and `runStatsLog` — parallel statistics collection.

**Key functions:**

| Function               | Description                                                    |
|------------------------|----------------------------------------------------------------|
| `runStatsDashboard`    | Full-file stats, no filters, data-parallel                     |
| `runStatsLog`          | Filtered stats with level/channel/search pre-filters           |
| `findChunkBoundaries`  | Splits mmap data into N chunks aligned at entry boundaries     |
| `scanChunkEntries`     | Iterates entries in a mmap chunk                               |
| `processMmap`          | Entry assembly from mmap data (for channel-based fallback)     |
| `processScanner`       | Entry assembly from bufio.Scanner (non-mmap fallback)          |
| `mergeLocalStats`      | Thread-safe merge of per-goroutine local stats                 |

**Processing pipeline (mmap / gzip path):**
1. If `.gz` — decompress fully into memory; otherwise mmap the file. Split data into `runtime.NumCPU()` chunks at entry boundaries
2. Each goroutine scans its chunk independently
3. Try `parser.ExtractStatsFields` (fast path, no allocation)
4. Fallback to `parser.Parse` if fast path fails
5. Accumulate into per-goroutine local maps
6. Merge all local maps into shared `Stats` under mutex

**Processing pipeline (scanner fallback):**
1. Producer goroutine reads lines, assembles entries, sends batches via channel
2. N worker goroutines consume batches, same fast-path/fallback logic
3. Merge into shared `Stats`

---

### mmap.go

Low-level OS primitives for memory-mapped I/O.

| Function             | Description                                                |
|----------------------|------------------------------------------------------------|
| `Mmap(f)`            | Memory-maps file read-only (`PROT_READ`, `MAP_SHARED`)    |
| `Munmap(data)`       | Unmaps previously mapped region                            |
| `MadviseSequential`  | Hints kernel for sequential read-ahead (`MADV_SEQUENTIAL`) |
| `unsafeString(b)`    | Zero-copy `[]byte` to `string` via `unsafe.String`        |

---

### reader.go

File-based line readers used as fallback when mmap is unavailable.

| Function             | Description                                          |
|----------------------|------------------------------------------------------|
| `forwardLineReader`  | Reads lines sequentially via `bufio.Scanner` (10 MB buffer) |
| `reverseLineReader`  | Reads lines in reverse using 64 KB chunk-based seeking |

Both accept a `callback func([]byte) bool` — return `false` to stop early.

---

### gz.go

Transparent gzip decompression support for `.gz` log files.

| Function       | Description                                                    |
|----------------|----------------------------------------------------------------|
| `isGzipFile`   | Returns true if file path ends with `.gz`                      |
| `readGzipFull` | Decompresses entire `.gz` file into memory as `[]byte`         |
| `stripGzExt`   | Removes `.gz` extension from filename (for display in output)  |

When a `.gz` file is passed via `--file`, the tool decompresses it fully into memory and uses the same mmap-style code paths (binary search, chunk-parallel stats, forward/reverse iteration). This avoids separate code paths for compressed files at the cost of higher memory usage.

**Limitations:**
- The entire decompressed content is held in memory
- File size in stats output reflects the compressed `.gz` file size, not the decompressed size

---

### perf_test.go

Go benchmarks for performance-critical code paths.

| Benchmark                          | What it measures                              |
|------------------------------------|-----------------------------------------------|
| `BenchmarkParseFastNoTime`         | Fast-path parse (no context, no time parsing) |
| `BenchmarkParseFullNoTime`         | Full parse with JSON context extraction       |
| `BenchmarkContainsSearchValueASCII`| ASCII case-insensitive substring search       |
| `BenchmarkParseHourBucket`         | Hour bucket extraction from timestamp         |

Run with: `go test -bench=. -benchmem`

---

## Gzip Support

The tool transparently handles `.gz` compressed log files. When `--file` points to a `.gz` file:

1. The file is decompressed entirely into memory using `compress/gzip`
2. Auto-detection reads decompressed lines (gzip is decoded before parser probing)
3. All modes (`logs`, `stat_dashboard`, `stat_log`) work identically to uncompressed files
4. The decompressed data uses the same mmap-style code paths (binary search, parallel chunk processing, forward/reverse iteration)
5. The `file` field in output entries uses the filename without the `.gz` extension (e.g. `error.log.gz` → `error.log`)

**Example:**

```bash
./log-parser --file /var/log/nginx/access.log.1.gz --mode logs --limit 10
./log-parser --file /var/log/syslog.gz --mode stats
```

**Note:** The entire decompressed content is held in memory. For very large compressed files (multi-GB when decompressed), ensure sufficient RAM is available.

---

## Supported Log Formats

| Parser           | Format                                      | Example                                                                   |
|------------------|---------------------------------------------|---------------------------------------------------------------------------|
| `monolog`        | Monolog/Symfony/Laravel                      | `[2026-03-29T09:44:14+00:00] app.INFO: message {} {}`                    |
| `nginx_access`   | Nginx Combined Log Format                   | `127.0.0.1 - - [29/Mar/2026:10:00:00 +0000] "GET / HTTP/1.1" 200 1234`  |
| `nginx_error`    | Nginx error log                             | `2015/06/11 09:44:14 [error] 1234#5678: *9 message`                      |
| `apache_access`  | Apache Combined/Common Log Format           | `127.0.0.1 - - [29/Mar/2026:11:00:00 +0000] "GET / HTTP/1.1" 200 225`   |
| `syslog`         | BSD syslog                                  | `Oct 11 22:14:15 host service[123]: message`                             |
| `auth`           | auth.log (RFC3339 syslog)                   | `2026-03-29T00:05:43.791811+01:00 HOST sudo: message`                    |
| `kern`           | kern.log (RFC3339 syslog)                   | `2026-03-29T00:59:20.294161+01:00 HOST kernel: message`                  |
| `php_fpm`        | php-fpm log (RFC3339 syslog)                | `2026-03-29T00:59:20+01:00 HOST php-fpm[123]: message`                   |
| `php_error`      | PHP error log                               | `[2026-03-29T09:44:14+00:00] PHP Fatal error: message`                   |
| `supervisord`    | Supervisord log                             | `2026-03-27 21:04:56,966 CRIT message`                                   |
| `mysql`          | MySQL error/general log                     | `2020-08-06T14:25:02.835618Z 0 [Note] [MY-012487] [InnoDB] message`     |
| `json`           | Structured JSON lines                       | `{"timestamp":"...","level":"INFO","message":"..."}`                     |

---

## Parser Selection

### Auto-detection order

When `--parser` and `--pattern` are both omitted, the tool reads up to 10 lines from the file and tries parsers in this order (most specific first):

1. `nginx_error` — `YYYY/MM/DD HH:MM:SS [level]`
2. `supervisord` — comma at position 19 (`YYYY-MM-DD HH:MM:SS,mmm`)
3. `mysql` — RFC3339 with Z suffix + `[Level]` brackets
4. `php_error` — `[timestamp] PHP ...`
5. `monolog` — `[timestamp] channel.LEVEL:`
6. `nginx_access` — `[DD/Mon/YYYY:HH:MM:SS]` + quoted request
7. `auth` — RFC3339 syslog + sudo/sshd/pam keywords
8. `kern` — RFC3339 syslog + `kernel:`
9. `php_fpm` — RFC3339 syslog + php/fpm keywords
10. `syslog` — `Mon DD HH:MM:SS`
11. `json` — `{...}` with level/message keys

### Shared logic between parsers

| Shared code file             | Reused by                                         |
|------------------------------|---------------------------------------------------|
| `parser_access_common.go`    | `NginxAccessParser`, `ApacheAccessParser`          |
| `parser_rfc3339_syslog.go`   | `AuthLogParser`, `KernLogParser`, `PhpFpmParser`   |
| `parser_syslog.go` (`syslogLevelFromMessage`) | `SyslogParser`, `AuthLogParser`, `KernLogParser`, `PhpFpmParser` |
| `parser_nginx.go` (`monthToNum`, `lookupMonth`, `statusToLevel`) | All access log parsers, `SyslogParser` |
| `parser.go` (utilities)      | All parsers                                        |

---

## Performance Techniques

| Technique                       | Where used           | Description                                             |
|---------------------------------|----------------------|---------------------------------------------------------|
| Memory-mapped I/O               | `logs.go`, `stats.go`| Avoids kernel read+copy syscalls                       |
| `MADV_SEQUENTIAL`               | `mmap.go`            | Kernel read-ahead hint for sequential scans            |
| Data-parallel processing        | `stats.go`           | Each goroutine scans its own mmap chunk independently  |
| Binary search for cursor        | `logs.go`            | O(log n) offset finding for cursor-based pagination    |
| `sync.Pool` for LogEntry        | `parser.go`          | Eliminates GC pressure from entry allocation           |
| `unsafe.String` zero-copy       | `mmap.go`            | No allocation for byte-to-string on mmap-backed slices |
| Two-tier parsing                | All parsers          | `ExtractStatsFields` (fast, no alloc) vs `Parse` (full)|
| Byte-level pre-filters          | `logs.go`, `stats.go`| Reject non-matching lines before any parsing           |
| SIMD-accelerated byte search    | `parser.go`          | `bytes.IndexByte`/`strings.IndexByte` for first-char scan |
| Cached timestamp layout         | `parser.go`          | `atomic.Value` caches last successful layout           |
| Month lookup table              | `parser_nginx.go`    | Array indexed by letter-pair sum, no map overhead      |
| Batch pooling                   | `stats.go`           | `sync.Pool` for `[][]byte` batch slices                |
| Buffered output                 | `logs.go`            | `bufio.NewWriterSize(stdout, 64KB)` reduces syscalls   |
