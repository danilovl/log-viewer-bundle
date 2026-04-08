<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters,
    LogViewerStats
};
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

readonly class GoLogClient
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private CompositeLogParser $compositeLogParser
    ) {}

    /**
     * @return list<LogEntry>
     */
    public function getLogs(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        int $limit = 50,
        ?string $cursor = null,
        string $sortDir = 'desc',
        int $offset = 0,
        ?string $hostName = null
    ): array {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return [];
        }

        $binaryPath = $this->getBinaryPath();
        $arguments = new GoLogArguments(
            binaryPath: $binaryPath,
            filePath: $filePath,
            configurationProvider: $this->configurationProvider,
            compositeLogParser: $this->compositeLogParser
        );

        $arguments
            ->addPagination(
                limit: $limit,
                offset: $offset,
                cursor: $cursor,
                sortDir: $sortDir
            )
            ->addHost($hostName)
            ->addParser($parserType)
            ->addFilters($filters);

        $process = new Process($arguments->toArray());
        $process->setTimeout(30);
        $process->run();

        $isSuccessful = $process->isSuccessful();
        if (!$isSuccessful) {
            $errorOutput = $process->getErrorOutput();

            throw new RuntimeException('Go parser error: ' . $errorOutput);
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $filteredLines = array_values(array_filter($lines));

        return array_map($this->mapLogEntry(...), $filteredLines);
    }

    public function getStats(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        ?string $timelineFormat = null,
        ?string $hostName = null
    ): LogViewerStats {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return LogViewerStats::fromArray([
                'size' => 0,
                'updatedAt' => is_file($filePath) ? date('Y-m-d H:i:s', (int) filemtime($filePath)) : date('Y-m-d H:i:s'),
                'calculatedAt' => date('Y-m-d H:i:s'),
            ]);
        }

        $binaryPath = $this->getBinaryPath();
        $hasFilters = $filters !== null && $filters->hasFilters;

        $arguments = new GoLogArguments(
            binaryPath: $binaryPath,
            filePath: $filePath,
            configurationProvider: $this->configurationProvider,
            compositeLogParser: $this->compositeLogParser
        );

        $arguments->addMode($hasFilters ? 'stat_filter' : 'stats')
            ->addHost($hostName)
            ->addParser($parserType)
            ->addFilters($filters);

        $process = new Process($arguments->toArray());
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            $message = 'Go parser stats error: ' . $errorOutput;

            throw new RuntimeException($message);
        }

        $output = $process->getOutput();
        $trimmedOutput = mb_trim($output);
        $decoded = json_decode($trimmedOutput, true);
        $data = is_array($decoded) ? $decoded : [];

        $filesize = (int) filesize($filePath);
        $mtime = (int) filemtime($filePath);

        $data['size'] ??= $filesize;
        $data['updatedAt'] ??= date('Y-m-d H:i:s', $mtime);
        $data['calculatedAt'] ??= date('Y-m-d H:i:s');

        $stats = LogViewerStats::fromArray($data);

        if ($timelineFormat !== null) {
            $newTimeline = [];
            foreach ($stats->timeline as $timestamp => $count) {
                $normalizedTs = DateNormalizer::normalize((string) $timestamp);
                if (str_starts_with($normalizedTs, '0000') || mb_strlen($normalizedTs) < 10) {
                    continue;
                }

                $key = DateNormalizer::getTimelineKey((string) $timestamp, $timelineFormat);

                $newTimeline[$key] = ($newTimeline[$key] ?? 0) + $count;
            }

            $stats->timeline = $newTimeline;
        } else {
            $stats->timeline = [];
        }

        return $stats;
    }

    public function getCount(string $filePath, ?string $parserType, ?LogViewerFilters $filters = null, ?string $hostName = null): int
    {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return 0;
        }

        $binaryPath = $this->getBinaryPath();
        $arguments = new GoLogArguments(
            binaryPath: $binaryPath,
            filePath: $filePath,
            configurationProvider: $this->configurationProvider,
            compositeLogParser: $this->compositeLogParser
        );

        $arguments
            ->addMode('count')
            ->addHost($hostName)
            ->addParser($parserType)
            ->addFilters($filters);

        $process = new Process($arguments->toArray());
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            $message = 'Go parser count error: ' . $errorOutput;

            throw new RuntimeException($message);
        }

        $output = $process->getOutput();
        $trimmedOutput = mb_trim($output);
        $decoded = json_decode($trimmedOutput, true);
        $result = is_array($decoded) ? $decoded : [];

        $total = $result['total'] ?? 0;
        if (!is_scalar($total)) {
            return 0;
        }

        return (int) $total;
    }

    /**
     * @return array{lines: list<string>, page: int, limit: int, totalLines: int}
     */
    public function getFileContent(
        string $filePath,
        int $page = 1,
        int $limit = 100,
        ?int $line = null,
        ?string $hostName = null
    ): array {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return [
                'lines' => [],
                'page' => $page,
                'limit' => $limit,
                'totalLines' => 0
            ];
        }

        $offset = ($page - 1) * $limit;
        if ($line !== null) {
            $offset = $line;
        }

        $binaryPath = $this->getBinaryPath();
        $arguments = new GoLogArguments(
            binaryPath: $binaryPath,
            filePath: $filePath,
            configurationProvider: $this->configurationProvider,
            compositeLogParser: $this->compositeLogParser
        );

        $arguments
            ->addMode('file_content')
            ->addHost($hostName)
            ->addPagination(
                limit: $limit,
                offset: $offset,
                cursor: null,
                sortDir: 'asc'
            );

        $process = new Process($arguments->toArray());
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();

            throw new RuntimeException('Go parser file_content error: ' . $errorOutput);
        }

        $output = $process->getOutput();
        $decoded = json_decode($output, true);

        /** @var array{lines: list<string>, page: int, limit: int, totalLines: int} $data */
        $data = is_array($decoded) ? $decoded : [];

        return $data;
    }

    public function identify(string $filePath, ?string $hostName = null): ?string
    {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return null;
        }

        $binaryPath = $this->getBinaryPath();
        $arguments = new GoLogArguments(
            binaryPath: $binaryPath,
            filePath: $filePath,
            configurationProvider: $this->configurationProvider,
            compositeLogParser: $this->compositeLogParser
        );
        $arguments
            ->addMode('identify')
            ->addHost($hostName);

        $process = new Process($arguments->toArray());
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = $process->getOutput();
        $goParserName = mb_trim($output);
        if ($goParserName === '') {
            return null;
        }

        return $this->compositeLogParser->getParserNameByGoName($goParserName);
    }

    private function mapLogEntry(string $line): LogEntry
    {
        $trimmedLine = mb_trim($line);
        $decoded = json_decode($trimmedLine, true);
        $data = is_array($decoded) ? $decoded : [];

        $timestamp = $data['timestamp'] ?? '';
        $level = $data['level'] ?? '';
        $channel = $data['channel'] ?? '';
        $message = $data['message'] ?? '';
        $file = $data['file'] ?? '';
        $sql = $data['sql'] ?? null;

        /** @var array<string, mixed>|null $parameters */
        $parameters = $data['parameters'] ?? null;

        /** @var array<string, mixed>|null $context */
        $context = $data['context'] ?? null;

        $timestampString = is_scalar($timestamp) ? (string) $timestamp : '';
        $levelString = is_scalar($level) ? (string) $level : '';
        $channelString = is_scalar($channel) ? (string) $channel : '';
        $messageString = is_scalar($message) ? (string) $message : '';
        $fileString = is_scalar($file) ? (string) $file : '';
        $normalizedTimestamp = DateNormalizer::normalize($timestampString);
        $sqlString = is_scalar($sql) ? (string) $sql : null;
        $parametersArray = is_array($parameters) ? $parameters : null;
        $contextArray = is_array($context) ? $context : null;
        $lineNumber = $data['lineNumber'] ?? null;
        $lineNumberInt = is_numeric($lineNumber) ? (int) $lineNumber : null;

        return new LogEntry(
            timestamp: $timestampString,
            level: $levelString,
            channel: $channelString,
            message: $messageString,
            file: $fileString,
            normalizedTimestamp: $normalizedTimestamp,
            sql: $sqlString,
            parameters: $parametersArray,
            context: $contextArray,
            lineNumber: $lineNumberInt
        );
    }

    private function getBinaryPath(): string
    {
        $binaryPathValue = $this->configurationProvider->parserGoBinaryPath;
        $binaryPath = mb_trim($binaryPathValue);

        if (!file_exists($binaryPath)) {
            $cwd = getcwd();
            $message = sprintf(
                'Go parser binary not found at: "%s". Current working directory: "%s".',
                $binaryPath,
                $cwd
            );

            throw new RuntimeException($message);
        }

        if (!is_executable($binaryPath)) {
            $message = sprintf('Go parser binary is not executable: "%s".', $binaryPath);

            throw new RuntimeException($message);
        }

        return $binaryPath;
    }
}
