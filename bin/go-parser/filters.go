package main

import (
	"fmt"
	"regexp"
	"strings"
	"time"
)

// entryFilter holds all filters shared by logs, stats and count modes.
type entryFilter struct {
	parser LogParser

	dFrom, dTo       time.Time
	dFromKey, dToKey int64

	levelUpper  string
	levelsUpper map[string]struct{}
	channel     string

	search              string
	searchRegex         bool
	searchCaseSensitive bool
	searchASCII         bool
	searchRe            *regexp.Regexp

	// Raw-line pre-filters: only enabled when the value is guaranteed to appear
	// literally in the raw line, otherwise they would drop valid entries.
	prefilterLevel   []byte
	prefilterChannel []byte
	prefilterSearch  bool
}

func newEntryFilter(parser LogParser, level, levels, channel, search string, searchRegex, searchCaseSensitive bool, dateFrom, dateTo string) (*entryFilter, error) {
	f := &entryFilter{
		parser:              parser,
		levelUpper:          strings.ToUpper(level),
		channel:             channel,
		searchRegex:         searchRegex,
		searchCaseSensitive: searchCaseSensitive,
	}

	if dateFrom != "" {
		// An unparseable date disables the filter (historical behaviour).
		if f.dFrom = parseTimestamp(dateFrom); !f.dFrom.IsZero() {
			f.dFromKey = wallKey(f.dFrom)
		}
	}
	if dateTo != "" {
		if f.dTo = parseTimestamp(dateTo); !f.dTo.IsZero() {
			f.dToKey = wallKey(f.dTo)
		}
	}

	if levels != "" {
		f.levelsUpper = make(map[string]struct{})
		for _, s := range strings.Split(levels, ",") {
			f.levelsUpper[strings.ToUpper(strings.TrimSpace(s))] = struct{}{}
		}
	}

	if search != "" {
		if searchRegex {
			pStr := search
			if !searchCaseSensitive && !strings.HasPrefix(pStr, "(?i)") {
				pStr = "(?i)" + pStr
			}
			re, err := regexp.Compile(pStr)
			if err != nil {
				return nil, fmt.Errorf("invalid search regex: %v", err)
			}
			f.searchRe = re
			f.search = search
		} else if searchCaseSensitive {
			f.search = search
		} else {
			f.search = strings.ToLower(search)
		}
		f.searchASCII = isASCIIString(f.search)
		f.prefilterSearch = rawSearchPrefilterSafe(parser, search, searchRegex)
	}

	literalLevel, literalChannel := literalFields(parser)
	if level != "" && literalLevel && isASCIIString(level) {
		f.prefilterLevel = []byte(strings.ToLower(level))
	}
	if channel != "" && literalChannel && isASCIIString(channel) {
		f.prefilterChannel = []byte(strings.ToLower(channel))
	}

	return f, nil
}

// literalFields reports whether a parser's level / channel are copied verbatim
// from the raw line (so that a raw substring check is a valid pre-filter).
func literalFields(parser LogParser) (level, channel bool) {
	switch p := parser.(type) {
	case *MonologParser:
		return true, true
	case *UniversalParser:
		if p.isAccessLog {
			return false, false
		}
		return true, true
	}
	return false, false
}

// rawSearchPrefilterSafe reports whether matching the search against the raw line
// is a strict superset of matching it against the parsed message / SQL.
func rawSearchPrefilterSafe(parser LogParser, search string, isRegex bool) bool {
	if strings.ContainsAny(search, "\"\\") {
		// JSON-encoded context (SQL, JSON logs) escapes these characters.
		return false
	}
	if _, isJSON := parser.(*JSONLogParser); isJSON {
		if !isASCIIString(search) || strings.Contains(search, "/") {
			return false
		}
	}
	if isRegex {
		// Anchors and word boundaries behave differently on the full raw line.
		if strings.ContainsAny(search, "^$") || strings.Contains(search, `\A`) ||
			strings.Contains(search, `\z`) || strings.Contains(search, `\b`) || strings.Contains(search, `\B`) {
			return false
		}
	}
	return true
}

func (f *entryFilter) hasSearch() bool { return f.search != "" }

// prefilter performs cheap byte-level checks on the raw entry.
func (f *entryFilter) prefilter(line []byte) bool {
	if isBlank(line) {
		return false
	}
	if f.prefilterLevel != nil && !containsFoldASCIIBytes(line, f.prefilterLevel) {
		return false
	}
	if f.prefilterChannel != nil && !containsFoldASCIIBytes(line, f.prefilterChannel) {
		return false
	}
	if f.search != "" && f.prefilterSearch {
		if !containsSearchValueBytes(line, f.search, f.searchRegex, f.searchCaseSensitive, f.searchASCII, f.searchRe) {
			return false
		}
	}
	return true
}

// isOrphan reports entries that are neither a real entry start nor have a timestamp
// (e.g. continuation lines before the first entry of a chunk).
func (f *entryFilter) isOrphan(line []byte, entry *LogEntry) bool {
	return entry.Timestamp == "" && !f.parser.IsNewEntry(line)
}

// matchDate checks the date range using wall-clock time (seconds precision),
// exactly like the former "2006-01-02 15:04:05" string comparison.
func (f *entryFilter) matchDate(entry *LogEntry) bool {
	if f.dFromKey == 0 && f.dToKey == 0 {
		return true
	}
	if entry.Time.Year() <= 1 {
		return true
	}
	k := wallKey(entry.Time)
	if f.dFromKey != 0 && k < f.dFromKey {
		return false
	}
	if f.dToKey != 0 && k > f.dToKey {
		return false
	}
	return true
}

func (f *entryFilter) matchFields(entry *LogEntry) bool {
	if f.levelUpper != "" && entry.Level != f.levelUpper {
		return false
	}
	if f.levelsUpper != nil {
		if _, ok := f.levelsUpper[entry.Level]; !ok {
			return false
		}
	}
	if f.channel != "" && !strings.EqualFold(entry.Channel, f.channel) {
		return false
	}
	return true
}

// matchSearch must be called on an entry parsed with full=true.
func (f *entryFilter) matchSearch(entry *LogEntry) bool {
	if f.search == "" {
		return true
	}
	if containsSearchValue(entry.Message, f.search, f.searchRegex, f.searchCaseSensitive, f.searchASCII, f.searchRe) {
		return true
	}
	return entry.SQL != "" && containsSearchValue(entry.SQL, f.search, f.searchRegex, f.searchCaseSensitive, f.searchASCII, f.searchRe)
}

// match runs the whole filter chain. It returns the parsed entry (caller must PutEntry)
// or nil. The entry is fully parsed (context/SQL) only when a search filter is set.
func (f *entryFilter) match(line []byte, fileName string) *LogEntry {
	if !f.prefilter(line) {
		return nil
	}
	full := f.search != ""
	entry := f.parser.Parse(line, fileName, full, true)
	if entry == nil {
		return nil
	}
	if f.isOrphan(line, entry) || !f.matchDate(entry) || !f.matchFields(entry) || !f.matchSearch(entry) {
		PutEntry(entry)
		return nil
	}
	return entry
}

func isBlank(line []byte) bool {
	for _, b := range line {
		if b != ' ' && b != '\t' && b != '\r' && b != '\n' {
			return false
		}
	}
	return true
}

// wallKey packs the wall-clock time (in the entry's own zone) into a sortable integer.
func wallKey(t time.Time) int64 {
	y, mo, d := t.Date()
	h, mi, s := t.Clock()
	return ((((int64(y)*13+int64(mo))*32+int64(d))*24+int64(h))*60+int64(mi))*60 + int64(s)
}

// hourKey packs YYYY-MM-DDTHH into an integer for allocation-free timeline counting.
func hourKey(t time.Time) int64 {
	y, mo, d := t.Date()
	return ((int64(y)*13+int64(mo))*32+int64(d))*24 + int64(t.Hour())
}

func hourKeyString(k int64) string {
	h := k % 24
	k /= 24
	d := k % 32
	k /= 32
	mo := k % 13
	y := k / 13
	var buf [13]byte
	buf[0] = byte('0' + y/1000%10)
	buf[1] = byte('0' + y/100%10)
	buf[2] = byte('0' + y/10%10)
	buf[3] = byte('0' + y%10)
	buf[4] = '-'
	buf[5] = byte('0' + mo/10)
	buf[6] = byte('0' + mo%10)
	buf[7] = '-'
	buf[8] = byte('0' + d/10)
	buf[9] = byte('0' + d%10)
	buf[10] = 'T'
	buf[11] = byte('0' + h/10)
	buf[12] = byte('0' + h%10)
	return string(buf[:])
}

// keyFunc extracts a sortable key from the first line of an entry.
type keyFunc func(firstLine []byte) (int64, bool)

func entryTimeKey(parser LogParser, wall bool) keyFunc {
	return func(line []byte) (int64, bool) {
		e := parser.Parse(line, "", false, true)
		if e == nil {
			return 0, false
		}
		t := e.Time
		PutEntry(e)
		if t.Year() <= 1 {
			return 0, false
		}
		if wall {
			return wallKey(t), true
		}
		return t.UnixNano(), true
	}
}

// lowerBound returns the offset of the first entry in data[lo:hi] whose key >= target,
// assuming entries are (roughly) sorted by time. ok=false means timestamps could not be
// read and the caller must not narrow the range.
func lowerBound(data []byte, lo, hi int, target int64, isNewEntry func([]byte) bool, key keyFunc) (int, bool) {
	const probeTries = 8

	// probe returns key of the first parseable entry starting in [pos, limit).
	probe := func(pos, limit int) (int, int64, int) {
		for i := 0; i < probeTries; i++ {
			p := entryStartAtOrAfter(data, pos, limit, isNewEntry)
			if p >= limit {
				return -1, 0, 0
			}
			if k, ok := key(firstLine(data, p)); ok {
				return p, k, 1
			}
			pos = p + 1
		}
		return -1, 0, -1
	}

	l, h := lo, hi
	for h-l > 64*1024 {
		mid := l + (h-l)/2
		p, k, st := probe(mid, h)
		if st == -1 {
			return 0, false
		}
		if p < 0 {
			h = mid
			continue
		}
		if k < target {
			l = p + 1
		} else {
			h = p
		}
	}

	// Exact linear phase: first parseable entry with key >= target.
	pos := l
	if pos > lo {
		pos = entryStartAtOrAfter(data, pos, hi, isNewEntry)
	}
	unknown := 0
	for pos < hi {
		if k, ok := key(firstLine(data, pos)); ok {
			if k >= target {
				return pos, true
			}
			unknown = 0
		} else if unknown++; unknown > 10000 {
			return 0, false
		}
		pos = entryStartAtOrAfter(data, pos+1, hi, isNewEntry)
	}
	return hi, true
}

// searchRange narrows [0, len(data)) using date filters and an optional cursor.
// A tolerance window protects against slightly out-of-order entries; every entry
// is still checked by the regular filters afterwards.
func searchRange(data []byte, parser LogParser, f *entryFilter, cursor time.Time, sortAsc bool) (int, int) {
	const tolerance = time.Minute
	lo, hi := 0, len(data)
	isNewEntry := parser.IsNewEntry

	if !f.dFrom.IsZero() {
		if p, ok := lowerBound(data, lo, hi, wallKey(f.dFrom.Add(-tolerance)), isNewEntry, entryTimeKey(parser, true)); ok {
			lo = p
		}
	}
	if !f.dTo.IsZero() && lo < hi {
		if p, ok := lowerBound(data, lo, hi, wallKey(f.dTo.Add(tolerance))+1, isNewEntry, entryTimeKey(parser, true)); ok {
			hi = p
		}
	}
	if !cursor.IsZero() && lo < hi {
		if sortAsc {
			if p, ok := lowerBound(data, lo, hi, cursor.Add(-tolerance).UnixNano(), isNewEntry, entryTimeKey(parser, false)); ok {
				lo = p
			}
		} else {
			if p, ok := lowerBound(data, lo, hi, cursor.Add(tolerance).UnixNano(), isNewEntry, entryTimeKey(parser, false)); ok {
				hi = p
			}
		}
	}
	if lo > hi {
		lo = hi
	}
	return lo, hi
}
