<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use DateTimeImmutable;
use Generator;
use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters,
    LogViewerStats
};
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use RuntimeException;
use SplFileObject;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class LogFileReader
{
    public function __construct(
        private readonly CompositeLogParser $parser,
        private readonly ?RemoteLogReader $remoteLogReader = null
    ) {}

    /**
     * @return list<LogEntry>
     */
    public function getEntries(
        string $filePath,
        ?string $parserType,
        int $limit = 50,
        ?string $cursor = null,
        ?LogViewerFilters $filters = null,
        string $sortDir = 'desc',
        int $offset = 0,
        ?string $host = null
    ): array {
        $handle = $this->openFile($filePath, $host);

        $search = $filters?->search;
        $searchRegex = $filters !== null && $filters->searchRegex;
        $searchRegexPattern = $searchRegex ? $this->getSearchRegexPattern($filters) : null;
        $normalizedCursor = $this->normalizeCursor($cursor);
        $entries = [];

        try {
            $count = 0;
            $skipped = 0;

            // Local files: narrow the byte range by date filters / cursor (binary search).
            $startPos = 0;
            $endPos = null;
            if ($host === null) {
                [$startPos, $endPos] = $this->narrowRange($handle, $filePath, $parserType, $filters, $normalizedCursor, $sortDir);
            }

            if ($sortDir === 'desc') {
                if ($endPos !== null) {
                    $totalLines = $this->countLinesBefore($handle, $endPos);
                } else {
                    $totalLines = $this->getCount($filePath, $parserType, host: $host);
                }
                $lines = $this->readLinesReversed($handle, $totalLines, $endPos, $startPos);
            } else {
                $firstLine = $startPos > 0 ? $this->countLinesBefore($handle, $startPos) : 0;
                fseek($handle, $startPos);
                $lines = $this->readLinesForward($handle, $firstLine, $endPos);
            }

            foreach ($lines as $lineNumber => $line) {
                if ($line === '') {
                    continue;
                }
                if ($count >= $limit) {
                    break;
                }

                if ($search !== null && !$searchRegex && !$this->containsIgnoreCase($line, $search)) {
                    continue;
                }

                $entry = $this->parser->parse($line, $filePath, $parserType);
                $entry = $entry->withLineNumber($lineNumber);

                if ($filters !== null && !$this->applyFilters($entry, $filters, $normalizedCursor, $sortDir, $searchRegexPattern)) {
                    continue;
                }

                // The cursor must work without other filters too.
                if ($filters === null && $normalizedCursor !== null && !$this->matchCursor($entry, $normalizedCursor, $sortDir)) {
                    continue;
                }

                if ($skipped < $offset) {
                    $skipped++;

                    continue;
                }

                $entries[] = $entry;
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $entries;
    }

    public function getStats(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        ?string $host = null,
        ?string $timelineFormat = null
    ): LogViewerStats {
        $handle = $this->openFile($filePath, $host);

        $hasFilters = $filters !== null && $filters->hasFilters;
        $search = $filters?->search;

        $size = 0;
        $calculatedAt = date('Y-m-d H:i:s');
        $updatedAt = $calculatedAt;

        if ($host === null) {
            $size = (int) filesize($filePath);
            $mtime = (int) filemtime($filePath);
            $updatedAt = date('Y-m-d H:i:s', $mtime);
        } else {
            $fstatResult = fstat($handle);
            if ($fstatResult !== false) {
                $size = (int) $fstatResult['size'];
                $updatedAt = date('Y-m-d H:i:s', (int) $fstatResult['mtime']);
            }
        }

        $stats = new LogViewerStats(
            size: $size,
            updatedAt: $updatedAt,
            calculatedAt: $calculatedAt,
        );

        $searchRegex = $filters !== null && $filters->searchRegex;
        $searchRegexPattern = $searchRegex ? $this->getSearchRegexPattern($filters) : null;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($search !== null && !$searchRegex && !$this->containsIgnoreCase($line, $search)) {
                    continue;
                }

                $trimmedLine = $this->trimLine($line);
                if ($trimmedLine === '') {
                    continue;
                }

                $entry = $this->parser->parse($trimmedLine, $filePath, $parserType);
                if ($hasFilters && !$this->applyFilters($entry, $filters, null, 'desc', $searchRegexPattern)) {
                    continue;
                }

                $this->collectStats($stats, $entry, $timelineFormat);
            }
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    public function getCount(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        ?string $host = null
    ): int {
        $hasFilters = $filters !== null && $filters->hasFilters;

        if (!$hasFilters) {
            if ($host === null && file_exists($filePath)) {
                try {
                    $fileSize = (int) @filesize($filePath);
                    if ($fileSize === 0) {
                        return 0;
                    }

                    $file = new SplFileObject($filePath);
                    $file->seek(PHP_INT_MAX);
                    $total = $file->key();

                    $file->fseek(-1, SEEK_END);
                    if ($file->fread(1) !== "\n") {
                        $total++;
                    }

                    return $total;
                } catch (Throwable) {
                    // Fall back to manual count if SplFileObject fails.
                }
            }

            $handle = $this->openFile($filePath, $host);
            $total = 0;
            $lastChar = null;

            try {
                while (!feof($handle)) {
                    $buffer = fread($handle, 1_048_576);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }
                    $total += substr_count($buffer, "\n");
                    $lastChar = $buffer[-1];
                }

                if ($lastChar !== null && $lastChar !== "\n") {
                    $total++;
                }
            } finally {
                fclose($handle);
            }

            return $total;
        }

        $handle = $this->openFile($filePath, $host);
        $search = $filters->search;
        $searchRegex = $filters->searchRegex;
        $searchRegexPattern = $searchRegex ? $this->getSearchRegexPattern($filters) : null;

        $total = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($search !== null && !$searchRegex && !$this->containsIgnoreCase($line, $search)) {
                    continue;
                }

                $trimmedLine = $this->trimLine($line);
                if ($trimmedLine === '') {
                    continue;
                }

                $entry = $this->parser->parse($trimmedLine, $filePath, $parserType);
                if (!$this->applyFilters($entry, $filters, null, 'desc', $searchRegexPattern)) {
                    continue;
                }

                $total++;
            }
        } finally {
            fclose($handle);
        }

        return $total;
    }

    /**
     * @return array{entries: list<LogEntry>, position: int}
     */
    public function getNewEntries(
        string $filePath,
        ?string $parserType,
        int $lastPosition,
        ?LogViewerFilters $filters = null,
        ?string $host = null,
        int $limit = 100
    ): array {
        $handle = $this->openFile($filePath, $host);

        $currentSize = 0;
        if ($host === null) {
            clearstatcache(true, $filePath);
            $currentSize = (int) filesize($filePath);
        } else {
            $fstatResult = fstat($handle);
            if ($fstatResult !== false) {
                $currentSize = (int) $fstatResult['size'];
            }
        }

        if ($lastPosition > $currentSize) {
            $lastPosition = 0;
        }

        if ($lastPosition === $currentSize && $currentSize > 0) {
            fclose($handle);

            return ['entries' => [], 'position' => $currentSize];
        }

        $newPosition = $lastPosition;
        if ($lastPosition > 0) {
            fseek($handle, $lastPosition);
        } else {
            fseek($handle, 0, SEEK_END);
            $newPosition = ftell($handle) ?: 0;
            fclose($handle);

            return ['entries' => [], 'position' => $newPosition];
        }

        $hasFilters = $filters !== null && $filters->hasFilters;
        $search = $filters?->search;
        $searchRegex = $filters !== null && $filters->searchRegex;
        $searchRegexPattern = $searchRegex ? $this->getSearchRegexPattern($filters) : null;
        $entries = [];

        try {
            while (($line = fgets($handle)) !== false) {
                // The writer may still be in the middle of this line: keep the position
                // before it so that the complete line is read on the next poll.
                if (!str_ends_with($line, "\n")) {
                    break;
                }

                $trimmedLine = $this->trimLine($line);
                if ($trimmedLine === '') {
                    $newPosition = ftell($handle) ?: $newPosition;

                    continue;
                }

                if ($search !== null && !$searchRegex && !$this->containsIgnoreCase($line, $search)) {
                    $newPosition = ftell($handle) ?: $newPosition;

                    continue;
                }

                $entry = $this->parser->parse($trimmedLine, $filePath, $parserType);

                if ($hasFilters && !$this->applyFilters($entry, $filters, null, 'asc', $searchRegexPattern)) {
                    $newPosition = ftell($handle) ?: $newPosition;

                    continue;
                }

                $entries[] = $entry;
                $newPosition = ftell($handle) ?: $newPosition;

                if (count($entries) >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return ['entries' => $entries, 'position' => $newPosition];
    }

    private function entryTimestamp(LogEntry $entry): string
    {
        $entryTimestamp = $entry->normalizedTimestamp !== '' ? $entry->normalizedTimestamp : DateNormalizer::normalize($entry->timestamp);

        return $entryTimestamp !== '' ? $entryTimestamp : $entry->timestamp;
    }

    private function matchCursor(LogEntry $entry, string $normalizedCursor, string $sortDir): bool
    {
        $entryTimestamp = $this->entryTimestamp($entry);

        if ($sortDir === 'desc') {
            return $entryTimestamp < $normalizedCursor;
        }

        return $entryTimestamp > $normalizedCursor;
    }

    private function applyFilters(LogEntry $entry, LogViewerFilters $filters, ?string $normalizedCursor, string $sortDir, ?string $searchRegexPattern = null): bool
    {
        $entryTimestamp = $this->entryTimestamp($entry);

        if ($normalizedCursor !== null && !$this->matchCursor($entry, $normalizedCursor, $sortDir)) {
            return false;
        }

        if ($filters->dateFrom !== null && $entryTimestamp < $filters->dateFrom) {
            return false;
        }

        if ($filters->dateTo !== null && $entryTimestamp > $filters->dateTo) {
            return false;
        }

        if ($filters->level !== null && $entry->level !== $filters->level) {
            return false;
        }

        if (!empty($filters->levels) && !in_array($entry->level, $filters->levels, true)) {
            return false;
        }

        if ($filters->channel !== null && $entry->channel !== $filters->channel) {
            return false;
        }

        if ($filters->search !== null) {
            if ($filters->searchRegex) {
                $pattern = $searchRegexPattern ?? $this->getSearchRegexPattern($filters);

                $messageMatch = @preg_match($pattern, $entry->message) === 1;
                $sqlMatch = $entry->sql !== null && @preg_match($pattern, $entry->sql) === 1;
            } else {
                if ($filters->searchCaseSensitive) {
                    // Byte search is exact for any UTF-8 text.
                    $messageMatch = str_contains($entry->message, $filters->search);
                    $sqlMatch = $entry->sql !== null && str_contains($entry->sql, $filters->search);
                } else {
                    $messageMatch = $this->containsIgnoreCase($entry->message, $filters->search);
                    $sqlMatch = $entry->sql !== null && $this->containsIgnoreCase($entry->sql, $filters->search);
                }
            }

            if (!$messageMatch && !$sqlMatch) {
                return false;
            }
        }

        return true;
    }

    /**
     * Narrows [start, end) by date filters and cursor using binary search over the
     * (time-sorted) file. A tolerance of one minute protects against slightly
     * out-of-order lines; every line is still checked by applyFilters().
     *
     * @param resource $handle
     * @return array{0: int, 1: int|null}
     */
    private function narrowRange($handle, string $filePath, ?string $parserType, ?LogViewerFilters $filters, ?string $normalizedCursor, string $sortDir): array
    {
        $lower = $filters?->dateFrom;
        $upper = $filters?->dateTo;

        if ($normalizedCursor !== null) {
            if ($sortDir === 'asc' && ($lower === null || $normalizedCursor > $lower)) {
                $lower = $normalizedCursor;
            }
            if ($sortDir === 'desc' && ($upper === null || $normalizedCursor < $upper)) {
                $upper = $normalizedCursor;
            }
        }

        if ($lower === null && $upper === null) {
            return [0, null];
        }

        $stat = fstat($handle);
        $size = $stat !== false ? (int) $stat['size'] : 0;
        if ($size < 1_048_576) {
            return [0, null];
        }

        $start = 0;
        $end = null;

        $lowerTarget = $this->shiftTimestamp($lower, '-1 minute');
        if ($lowerTarget !== null) {
            $start = $this->findLineOffset($handle, $size, $filePath, $parserType, $lowerTarget, false);
        }

        $upperTarget = $this->shiftTimestamp($upper, '+1 minute');
        if ($upperTarget !== null) {
            $found = $this->findLineOffset($handle, $size, $filePath, $parserType, $upperTarget, true);
            $end = $found < $size ? max($found, $start) : null;
        }

        return [$start, $end];
    }

    private function shiftTimestamp(?string $timestamp, string $modifier): ?string
    {
        if ($timestamp === null || preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~', $timestamp) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($timestamp)->modify($modifier)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns the offset of the first line whose timestamp is >= target
     * (> target when $strict). Returns 0 / $size when timestamps cannot be read.
     *
     * @param resource $handle
     */
    private function findLineOffset($handle, int $size, string $filePath, ?string $parserType, string $target, bool $strict): int
    {
        $fallback = $strict ? $size : 0;
        $isBefore = static fn (string $ts): bool => $strict ? $ts <= $target : $ts < $target;

        // Reads lines from $pos and returns [lineStart, lineEnd, timestamp] of the first one with a timestamp
        // (null: no line in [pos, limit), []: no timestamps at all, e.g. a format without dates).
        $probe = function (int $pos, int $limit) use ($handle, $filePath, $parserType): ?array {
            fseek($handle, $pos);
            if ($pos > 0 && fgets($handle) === false) {
                return null;
            }

            for ($i = 0; $i < 1_000; $i++) {
                $lineStart = (int) ftell($handle);
                if ($lineStart >= $limit) {
                    return null;
                }

                $line = fgets($handle);
                if ($line === false) {
                    return null;
                }

                $ts = $this->lineTimestamp($line, $filePath, $parserType);
                if ($ts !== null) {
                    return [$lineStart, (int) ftell($handle), $ts];
                }
            }

            return [];
        };

        $lo = 0;
        $hi = $size;
        while ($hi - $lo > 65_536) {
            $mid = intdiv($lo + $hi, 2);
            $result = $probe($mid, $hi);
            if ($result === []) {
                return $fallback;
            }
            if ($result === null) {
                $hi = $mid;

                continue;
            }

            [$lineStart, $lineEnd, $ts] = $result;
            if ($isBefore($ts)) {
                $lo = $lineEnd;
            } else {
                $hi = $lineStart;
            }
        }

        // Exact linear phase from a known line start.
        fseek($handle, $lo);
        $unknown = 0;
        while (($lineStart = (int) ftell($handle)) < $size && ($line = fgets($handle)) !== false) {
            $ts = $this->lineTimestamp($line, $filePath, $parserType);
            if ($ts === null) {
                if (++$unknown > 10_000) {
                    return $fallback;
                }

                continue;
            }
            if (!$isBefore($ts)) {
                return $lineStart;
            }
        }

        return $size;
    }

    private function lineTimestamp(string $line, string $filePath, ?string $parserType): ?string
    {
        $line = $this->trimLine($line);
        if ($line === '') {
            return null;
        }

        $ts = $this->entryTimestamp($this->parser->parse($line, $filePath, $parserType));

        return preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~', $ts) === 1 ? $ts : null;
    }

    /**
     * @param resource $handle
     */
    private function countLinesBefore($handle, int $pos): int
    {
        fseek($handle, 0);
        $count = 0;
        // Position via ftell(): byte-exact even if string functions get rewritten to mb_*.
        while (($remaining = $pos - (int) ftell($handle)) > 0) {
            $chunk = fread($handle, min($remaining, 1_048_576));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $count += substr_count($chunk, "\n");
        }

        return $count;
    }

    private function normalizeCursor(?string $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }

        $normalizedCursor = DateNormalizer::normalize($cursor);

        return $normalizedCursor !== '' ? $normalizedCursor : $cursor;
    }

    /**
     * Same result as mb_stripos() !== false: byte search first, multibyte
     * case folding only when the text or the needle contains non-ASCII bytes.
     */
    private function containsIgnoreCase(string $line, string $search): bool
    {
        if (stripos($line, $search) !== false) {
            return true;
        }

        if (preg_match('~[\x80-\xFF]~', $search) !== 1 && preg_match('~[\x80-\xFF]~', $line) !== 1) {
            return false;
        }

        return mb_stripos($line, $search) !== false;
    }

    /**
     * Same result as mb_trim(), but avoids multibyte processing for ASCII edges.
     */
    private function trimLine(string $line): string
    {
        $trimmed = trim($line, " \f\n\r\t\v\0");
        if ($trimmed !== '' && (ord($trimmed[0]) >= 0x80 || ord($trimmed[-1]) >= 0x80)) {
            return mb_trim($trimmed);
        }

        return $trimmed;
    }

    private function getSearchRegexPattern(LogViewerFilters $filters): string
    {
        $flags = $filters->searchCaseSensitive ? '' : 'i';
        $search = str_replace('~', '\~', (string) $filters->search);
        $pattern = '~' . $search . '~' . $flags;

        if (@preg_match($pattern, '') === false) {
            throw new BadRequestHttpException('Invalid search regex.');
        }

        return $pattern;
    }

    private function collectStats(LogViewerStats $stats, LogEntry $entry, ?string $timelineFormat = null): void
    {
        $stats->total++;

        $level = $entry->level;
        $stats->levels[$level] = ($stats->levels[$level] ?? 0) + 1;

        $channel = $entry->channel;
        $stats->channels[$channel] = ($stats->channels[$channel] ?? 0) + 1;

        if ($timelineFormat === null) {
            return;
        }

        $timestamp = $entry->normalizedTimestamp;
        if (str_starts_with($timestamp, '0000') || strlen($timestamp) < 10) {
            return;
        }

        $key = DateNormalizer::getTimelineKey($timestamp, $timelineFormat);

        $stats->timeline[$key] = ($stats->timeline[$key] ?? 0) + 1;
    }

    /**
     * @return resource
     */
    private function openFile(string $filePath, ?string $host = null, ?int $offset = null)
    {
        if ($host !== null) {
            if ($this->remoteLogReader === null) {
                throw new RuntimeException("RemoteLogReader is not available, but host '$host' was requested.");
            }

            return $this->remoteLogReader->openFile($host, $filePath, $offset);
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open file: $filePath");
        }

        if ($offset !== null && $offset > 0) {
            fseek($handle, $offset);
        }

        return $handle;
    }

    /**
     * @param resource $handle
     * @return Generator<int, string>
     */
    private function readLinesForward($handle, int $lineNumber = 0, ?int $endPos = null): Generator
    {
        while (($endPos === null || (int) ftell($handle) < $endPos) && ($line = fgets($handle)) !== false) {
            yield $lineNumber++ => $this->trimLine($line);
        }
    }

    /**
     * @param resource $handle
     * @return Generator<int, string>
     */
    private function readLinesReversed($handle, int $totalLines, ?int $endPos = null, int $startPos = 0): Generator
    {
        if ($endPos === null) {
            fseek($handle, 0, SEEK_END);
            $pos = (int) ftell($handle);
        } else {
            $pos = $endPos;
        }
        $leftover = '';
        $lineNumber = $totalLines - 1;

        // The data ends with "\n" (end of file or a range ending at a line start):
        // the empty element after it is not a line and must not consume a line number.
        $skipFirst = true;

        while ($pos > $startPos) {
            $readSize = min($pos - $startPos, 65_536);
            if ($readSize < 1) {
                break;
            }
            $pos -= $readSize;
            fseek($handle, $pos);
            /** @var string $chunk */
            $chunk = fread($handle, $readSize);

            $lines = explode("\n", $chunk);
            $count = count($lines);
            $lines[$count - 1] .= $leftover;
            $leftover = $lines[0];

            for ($i = $count - 1; $i >= 1; $i--) {
                if ($skipFirst) {
                    $skipFirst = false;
                    if ($lines[$i] === '') {
                        continue;
                    }
                }

                yield $lineNumber-- => $this->trimLine($lines[$i]);
            }
        }

        if ($leftover !== '') {
            yield $lineNumber-- => $this->trimLine($leftover);
        }
    }
}
