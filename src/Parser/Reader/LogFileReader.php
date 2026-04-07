<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use Generator;
use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters,
    LogViewerStats
};
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use RuntimeException;
use SplFileObject;
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
        $entries = [];

        try {
            $count = 0;
            $skipped = 0;
            $lines = $sortDir === 'desc' ? $this->readLinesReversed($handle) : $this->readLinesForward($handle);

            foreach ($lines as $line) {
                if (!is_string($line) || $line === '') {
                    continue;
                }
                if ($count >= $limit) {
                    break;
                }

                if ($search !== null && !$searchRegex && mb_stripos($line, $search) === false) {
                    continue;
                }

                $entry = $this->parser->parse($line, $filePath, $parserType);

                if ($filters !== null && !$this->applyFilters($entry, $filters, $cursor, $sortDir, $searchRegexPattern)) {
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
                if ($search !== null && !$searchRegex && mb_stripos($line, $search) === false) {
                    continue;
                }

                $trimmedLine = mb_trim($line);
                if ($trimmedLine === '') {
                    continue;
                }

                $entry = $this->parser->parse($trimmedLine, $filePath, $parserType);
                if ($hasFilters && $filters !== null && !$this->applyFilters($entry, $filters, null, 'desc', $searchRegexPattern)) {
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
                    $buffer = fread($handle, 65_536);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }
                    $total += mb_substr_count($buffer, "\n");
                    $lastChar = $buffer[mb_strlen($buffer) - 1];
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
                if ($search !== null && !$searchRegex && mb_stripos($line, $search) === false) {
                    continue;
                }

                $trimmedLine = mb_trim($line);
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

        clearstatcache(true, $filePath);
        $currentSize = (int) filesize($filePath);

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

        clearstatcache(true, $filePath);
        $currentSize = (int) filesize($filePath);
        $hasFilters = $filters !== null && $filters->hasFilters;
        $search = $filters?->search;
        $searchRegex = $filters !== null && $filters->searchRegex;
        $searchRegexPattern = $searchRegex ? $this->getSearchRegexPattern($filters) : null;
        $entries = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmedLine = mb_trim($line);
                if ($trimmedLine === '') {
                    $newPosition = ftell($handle) ?: $newPosition;

                    continue;
                }

                if ($search !== null && !$searchRegex && mb_stripos($line, $search) === false) {
                    $newPosition = ftell($handle) ?: $newPosition;

                    continue;
                }

                $entry = $this->parser->parse($trimmedLine, $filePath, $parserType);

                if ($hasFilters && $filters !== null && !$this->applyFilters($entry, $filters, null, 'asc', $searchRegexPattern)) {
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

    private function applyFilters(LogEntry $entry, LogViewerFilters $filters, ?string $cursor, string $sortDir, ?string $searchRegexPattern = null): bool
    {
        $entryTimestamp = $entry->normalizedTimestamp !== '' ? $entry->normalizedTimestamp : DateNormalizer::normalize($entry->timestamp);
        $entryTimestamp = $entryTimestamp !== '' ? $entryTimestamp : $entry->timestamp;

        if ($cursor !== null) {
            $normalizedCursor = DateNormalizer::normalize($cursor);
            $normalizedCursor = $normalizedCursor !== '' ? $normalizedCursor : $cursor;

            if ($sortDir === 'desc' && $entryTimestamp >= $normalizedCursor) {
                return false;
            }
            if ($sortDir === 'asc' && $entryTimestamp <= $normalizedCursor) {
                return false;
            }
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
                    $messageMatch = mb_strpos($entry->message, $filters->search) !== false;
                    $sqlMatch = $entry->sql !== null && mb_strpos($entry->sql, $filters->search) !== false;
                } else {
                    $messageMatch = mb_stripos($entry->message, $filters->search) !== false;
                    $sqlMatch = $entry->sql !== null && mb_stripos($entry->sql, $filters->search) !== false;
                }
            }

            if (!$messageMatch && !$sqlMatch) {
                return false;
            }
        }

        return true;
    }

    private function getSearchRegexPattern(LogViewerFilters $filters): string
    {
        $flags = $filters->searchCaseSensitive ? '' : 'i';

        return '/' . str_replace('/', '\/', (string) $filters->search) . '/' . $flags;
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
        if (str_starts_with($timestamp, '0000') || mb_strlen($timestamp) < 10) {
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
     */
    private function readLinesForward($handle): Generator
    {
        while (($line = fgets($handle)) !== false) {
            yield mb_trim($line);
        }
    }

    /**
     * @param resource $handle
     */
    private function readLinesReversed($handle): Generator
    {
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        $leftover = '';

        while ($pos > 0) {
            $readSize = min($pos, 65_536);
            $pos -= $readSize;
            fseek($handle, $pos);
            /** @var string $chunk */
            $chunk = fread($handle, $readSize);

            $lines = explode("\n", $chunk);
            $count = count($lines);
            $lines[$count - 1] .= $leftover;
            $leftover = $lines[0];

            for ($i = $count - 1; $i >= 1; $i--) {
                yield mb_trim($lines[$i]);
            }
        }

        if ($leftover !== '') {
            yield mb_trim($leftover);
        }
    }
}
