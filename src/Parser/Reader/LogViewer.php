<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\Service\{
    ConfigurationProvider,
    FileContentReader
};
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser
};
use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters,
    LogViewerSource,
    LogViewerStats
};
use Danilovl\LogViewerBundle\Util\DateNormalizer;
use DateTimeImmutable;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\{
    ItemInterface,
    TagAwareCacheInterface
};

class LogViewer
{
    public const string CACHE_TAG = 'danilovl.log_viewer';

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly GoLogClient $goClient,
        private readonly LogFileReader $phpReader,
        private readonly FileContentReader $fileContentReader,
        private readonly LogSourceManager $sourceManager,
        private readonly TagAwareCacheInterface $cache,
        private readonly CompositeLogParser $compositeLogParser,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * @return list<LogEntry>
     */
    public function getEntries(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        int $limit = 50,
        ?string $cursor = null,
        string $sortDir = 'desc',
        int $offset = 0,
        ?string $host = null
    ): array {
        if ($this->isGoParserEnabled($parserType)) {
            try {
                return $this->goClient->getLogs(
                    filePath: $filePath,
                    parserType: $parserType,
                    filters: $filters,
                    limit: $limit,
                    cursor: $cursor,
                    sortDir: $sortDir,
                    offset: $offset,
                    hostName: $host
                );
            } catch (RuntimeException $e) {
                $message = sprintf('LogViewerBundle: Go parser error, fallback to PHP: %s', $e->getMessage());

                $this->logger?->error($message, [
                    'file' => $filePath,
                    'parser' => $parserType,
                    'host' => $host
                ]);
            }
        }

        return $this->phpReader->getEntries(
            filePath: $filePath,
            parserType: $parserType,
            limit: $limit,
            cursor: $cursor,
            filters: $filters,
            sortDir: $sortDir,
            offset: $offset,
            host: $host
        );
    }

    public function getStats(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        ?string $host = null,
        ?string $timelineFormat = null
    ): LogViewerStats {
        $hasFilters = $filters !== null && $filters->hasFilters;

        if ($this->configurationProvider->cacheStatisticEnabled &&
            !$hasFilters
        ) {
            $cacheParserType = $parserType ?? '';
            $cacheHost = $host ?? '';

            $cacheKey = sprintf('%s.stats.%s', self::CACHE_TAG, sha1($filePath . $cacheParserType . $cacheHost . ($timelineFormat ?? '')));

            /** @var LogViewerStats $cachedStats */
            $cachedStats = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filePath, $parserType, $filters, $host, $timelineFormat): LogViewerStats {
                $item->tag(self::CACHE_TAG);
                $item->expiresAfter($this->configurationProvider->cacheStatisticInterval);

                return $this->doGetStats($filePath, $parserType, $filters, $host, $timelineFormat);
            });

            return $cachedStats;
        }

        return $this->doGetStats($filePath, $parserType, $filters, $host, $timelineFormat);
    }

    public function getCount(string $filePath, ?string $parserType, ?LogViewerFilters $filters = null, ?string $host = null): int
    {
        $hasFilters = $filters !== null && $filters->hasFilters;

        if ($this->configurationProvider->cacheStatisticEnabled &&
            !$hasFilters
        ) {
            $cacheParserType = $parserType ?? '';
            $cacheHost = $host ?? '';

            $cacheKey = sprintf('%s.count.%s', self::CACHE_TAG, sha1($filePath . $cacheParserType . $cacheHost));

            /** @var int $cachedCount */
            $cachedCount = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filePath, $parserType, $filters, $host): int {
                $item->tag(self::CACHE_TAG);
                $item->expiresAfter($this->configurationProvider->cacheStatisticInterval);

                return $this->doGetCount($filePath, $parserType, $filters, $host);
            });

            return $cachedCount;
        }

        return $this->doGetCount($filePath, $parserType, $filters, $host);
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
        return $this->phpReader->getNewEntries(
            filePath: $filePath,
            parserType: $parserType,
            lastPosition: $lastPosition,
            filters: $filters,
            host: $host,
            limit: $limit
        );
    }

    private function doGetStats(
        string $filePath,
        ?string $parserType,
        ?LogViewerFilters $filters = null,
        ?string $host = null,
        ?string $timelineFormat = null
    ): LogViewerStats {
        if ($this->isGoParserEnabled($parserType)) {
            try {
                $stats = $this->goClient->getStats($filePath, $parserType, $filters, $timelineFormat, $host);
            } catch (RuntimeException $e) {
                $message = sprintf('LogViewerBundle: Go parser error (stats), fallback to PHP %s', $e->getMessage());

                $this->logger?->error($message, [
                    'file' => $filePath,
                    'parser' => $parserType,
                    'host' => $host
                ]);
                $stats = $this->phpReader->getStats($filePath, $parserType, $filters, $host, $timelineFormat);
            }
        } else {
            $stats = $this->phpReader->getStats($filePath, $parserType, $filters, $host, $timelineFormat);
        }

        if ($timelineFormat !== null) {
            $endRange = $stats->calculatedAt ? new DateTimeImmutable($stats->calculatedAt) : null;
            $stats->timeline = DateNormalizer::fillTimelineGaps($stats->timeline, $timelineFormat, null, $endRange);
        }

        return $stats;
    }

    private function doGetCount(string $filePath, ?string $parserType, ?LogViewerFilters $filters = null, ?string $host = null): int
    {
        if ($this->isGoParserEnabled($parserType)) {
            try {
                return $this->goClient->getCount($filePath, $parserType, $filters, $host);
            } catch (RuntimeException $e) {
                $message = sprintf('LogViewerBundle: Go parser error (count), fallback to PHP %s', $e->getMessage());

                $this->logger?->error($message, [
                    'file' => $filePath,
                    'parser' => $parserType,
                    'host' => $host
                ]);
            }
        }

        return $this->phpReader->getCount($filePath, $parserType, $filters, $host);
    }

    /**
     * @return list<LogViewerSource>
     */
    public function getSources(): array
    {
        return $this->sourceManager->getAllSources();
    }

    /**
     * @param array<string, int> $positions
     */
    public function saveWatcherPositions(array $positions, ?string $userIdentifier = null): void
    {
        $cacheKey = $this->getWatcherPositionsCacheKey($userIdentifier);
        $this->cache->get($cacheKey, static function (ItemInterface $item) use ($positions): array {
            $item->tag(self::CACHE_TAG);
            $item->expiresAfter(3_600);

            return $positions;
        }, INF);
    }

    /**
     * @return array<string, int>
     */
    public function loadWatcherPositions(?string $userIdentifier = null): array
    {
        $cacheKey = $this->getWatcherPositionsCacheKey($userIdentifier);

        /** @var array<string, int> $positions */
        $positions = $this->cache->get($cacheKey, static function (ItemInterface $item): array {
            $item->tag(self::CACHE_TAG);
            $item->expiresAfter(3_600);

            return [];
        });

        return $positions;
    }

    private function getWatcherPositionsCacheKey(?string $userIdentifier = null): string
    {
        $key = self::CACHE_TAG . '.watcher_positions';
        if ($userIdentifier !== null) {
            $key .= '.' . sha1($userIdentifier);
        }

        return $key;
    }

    /**
     * @return array{lines: list<string>, page: int, limit: int, totalLines: int}
     */
    public function getFileContent(
        string $filePath,
        ?string $parserType,
        int $page = 1,
        int $limit = 100,
        ?int $line = null,
        ?string $host = null
    ): array {
        if ($this->isGoParserEnabled($parserType)) {
            try {
                return $this->goClient->getFileContent($filePath, $page, $limit, $line, $host);
            } catch (RuntimeException $e) {
                $message = sprintf('LogViewerBundle: Go parser error (file_content), fallback to PHP: %s', $e->getMessage());

                $this->logger?->error($message, [
                    'file' => $filePath,
                    'parser' => $parserType,
                    'host' => $host
                ]);
            }
        }

        if ($host !== null) {
            return [
                'lines' => [],
                'page' => $page,
                'limit' => $limit,
                'totalLines' => 0
            ];
        }

        $lines = array_values($this->fileContentReader->readLines($filePath, $page, $limit, $line));
        if ($line !== null) {
            $page = (int) floor($line / $limit) + 1;
        }

        return [
            'lines' => $lines,
            'page' => $page,
            'limit' => $limit,
            'totalLines' => $this->fileContentReader->getTotalLines($filePath)
        ];
    }

    public function isGoParserEnabled(?string $parserType): bool
    {
        $parserGoEnabled = $this->configurationProvider->parserGoEnabled;
        if (!$parserGoEnabled) {
            return false;
        }

        if ($parserType === null) {
            return true;
        }

        $parser = $this->compositeLogParser->getParser($parserType);
        if ($parser === null) {
            return true;
        }

        return $this->compositeLogParser->isGoParserEnabled($parser);
    }
}
