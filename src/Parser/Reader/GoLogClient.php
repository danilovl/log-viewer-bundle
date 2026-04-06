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

class GoLogClient
{
    private const array KNOWN_PARSERS = [
        'monolog',
        'nginx_access',
        'nginx_error',
        'apache_access',
        'syslog',
        'auth',
        'kern',
        'php_fpm',
        'php_error',
        'supervisord',
        'mysql',
        'json'
    ];

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CompositeLogParser $compositeLogParser
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
        $cmd = [
            $binaryPath,
            '--file', $filePath,
            '--limit', (string) $limit,
            '--offset', (string) $offset,
            '--sort', $sortDir,
        ];

        if ($hostName !== null) {
            $this->addHostFlags($cmd, $hostName);
        }

        $parserName = $this->compositeLogParser->getParserName($parserType);
        if ($parserName !== null && in_array($parserName, self::KNOWN_PARSERS, true)) {
            $cmd[] = '--parser';
            $cmd[] = $parserName;
        }

        $pattern = $this->compositeLogParser->getPatternGo($parserType);
        if ($pattern !== null) {
            $cmd[] = '--pattern';
            $cmd[] = $pattern;
        }

        if ($cursor !== null) {
            $cmd[] = '--cursor';
            $cmd[] = $cursor;
        }

        if ($filters !== null) {
            if ($filters->level !== null) {
                $cmd[] = '--level';
                $cmd[] = $filters->level;
            }

            if (!empty($filters->levels)) {
                $cmd[] = '--levels';
                $cmd[] = implode(',', $filters->levels);
            }

            if ($filters->channel !== null) {
                $cmd[] = '--channel';
                $cmd[] = $filters->channel;
            }

            if ($filters->search !== null) {
                $cmd[] = '--search';
                $cmd[] = $filters->search;

                if ($filters->searchRegex) {
                    $cmd[] = '--search-regex';
                }

                if ($filters->searchCaseSensitive) {
                    $cmd[] = '--search-case-sensitive';
                }
            }
        }

        $process = new Process($cmd);
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

        $logs = array_map(static function (string $line): LogEntry {
            $decoded = json_decode(mb_trim($line), true);
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

            return new LogEntry(
                timestamp: is_scalar($timestamp) ? (string) $timestamp : '',
                level: is_scalar($level) ? (string) $level : '',
                channel: is_scalar($channel) ? (string) $channel : '',
                message: is_scalar($message) ? (string) $message : '',
                file: is_scalar($file) ? (string) $file : '',
                normalizedTimestamp: DateNormalizer::normalize(is_scalar($timestamp) ? (string) $timestamp : ''),
                sql: is_scalar($sql) ? (string) $sql : null,
                parameters: is_array($parameters) ? $parameters : null,
                context: is_array($context) ? $context : null,
            );
        }, $filteredLines);

        return $logs;
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

        $cmd = [
            $binaryPath,
            '--file', $filePath,
            '--mode', $hasFilters ? 'stat_filter' : 'stats',
        ];

        if ($hostName !== null) {
            $this->addHostFlags($cmd, $hostName);
        }

        $parserName = $this->compositeLogParser->getParserName($parserType);
        if ($parserName !== null && in_array($parserName, self::KNOWN_PARSERS, true)) {
            $cmd[] = '--parser';
            $cmd[] = $parserName;
        }

        $pattern = $this->compositeLogParser->getPatternGo($parserType);
        if ($pattern !== null) {
            $cmd[] = '--pattern';
            $cmd[] = $pattern;
        }

        if ($filters !== null) {
            if ($filters->level !== null) {
                $cmd[] = '--level';
                $cmd[] = $filters->level;
            }

            if (!empty($filters->levels)) {
                $cmd[] = '--levels';
                $cmd[] = implode(',', $filters->levels);
            }

            if ($filters->channel !== null) {
                $cmd[] = '--channel';
                $cmd[] = $filters->channel;
            }

            if ($filters->search !== null) {
                $cmd[] = '--search';
                $cmd[] = $filters->search;

                if ($filters->searchRegex) {
                    $cmd[] = '--search-regex';
                }

                if ($filters->searchCaseSensitive) {
                    $cmd[] = '--search-case-sensitive';
                }
            }
        }

        $process = new Process($cmd);
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

        $cmd = [
            $binaryPath,
            '--file', $filePath,
            '--mode', 'count',
        ];

        if ($hostName !== null) {
            $this->addHostFlags($cmd, $hostName);
        }

        $parserName = $this->compositeLogParser->getParserName($parserType);
        if ($parserName !== null && in_array($parserName, self::KNOWN_PARSERS, true)) {
            $cmd[] = '--parser';
            $cmd[] = $parserName;
        }

        $pattern = $this->compositeLogParser->getPatternGo($parserType);
        if ($pattern !== null) {
            $cmd[] = '--pattern';
            $cmd[] = $pattern;
        }

        if ($filters !== null) {
            if ($filters->level !== null) {
                $cmd[] = '--level';
                $cmd[] = $filters->level;
            }

            if (!empty($filters->levels)) {
                $cmd[] = '--levels';
                $cmd[] = implode(',', $filters->levels);
            }

            if ($filters->channel !== null) {
                $cmd[] = '--channel';
                $cmd[] = $filters->channel;
            }

            if ($filters->search !== null) {
                $cmd[] = '--search';
                $cmd[] = $filters->search;

                if ($filters->searchRegex) {
                    $cmd[] = '--search-regex';
                }

                if ($filters->searchCaseSensitive) {
                    $cmd[] = '--search-case-sensitive';
                }
            }
        }

        $process = new Process($cmd);
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

    public function identify(string $filePath, ?string $hostName = null): ?string
    {
        if ($hostName === null && (!is_file($filePath) || filesize($filePath) === 0)) {
            return null;
        }

        $binaryPath = $this->getBinaryPath();
        $cmd = [
            $binaryPath,
            '--file', $filePath,
            '--mode', 'identify'
        ];

        if ($hostName !== null) {
            $this->addHostFlags($cmd, $hostName);
        }

        $process = new Process($cmd);
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

    /**
     * @param string[] $cmd
     */
    private function addHostFlags(array &$cmd, string $hostName): void
    {
        $hostConfig = $this->configurationProvider->findRemoteHost($hostName);
        if ($hostConfig === null) {
            return;
        }

        $cmd[] = '--host';
        $cmd[] = $hostConfig->host;
        $cmd[] = '--port';
        $cmd[] = (string) $hostConfig->port;
        $cmd[] = '--host-type';
        $cmd[] = $hostConfig->type;

        if ($hostConfig->user !== null) {
            $cmd[] = '--user';
            $cmd[] = $hostConfig->user;
        }

        if ($hostConfig->password !== null) {
            $cmd[] = '--password';
            $cmd[] = $hostConfig->password;
        }

        if ($hostConfig->sshKey !== null) {
            $cmd[] = '--ssh-key';
            $cmd[] = $hostConfig->sshKey;
        }
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
