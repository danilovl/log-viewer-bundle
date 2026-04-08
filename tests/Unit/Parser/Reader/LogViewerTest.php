<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters,
    LogViewerSource,
    LogViewerStats
};
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    Reader\GoLogClient,
    Reader\LogFileReader,
    Reader\LogSourceManager,
    Reader\LogViewer
};
use Danilovl\LogViewerBundle\Tests\Mock\Parser\{
    CustomNoGoParser,
    CustomGoParser
};
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use PHPUnit\Framework\Attributes\{
    DataProvider,
    AllowMockObjectsWithoutExpectations
};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Generator;

#[AllowMockObjectsWithoutExpectations]
final class LogViewerTest extends TestCase
{
    private LogViewer $viewer;

    private GoLogClient&MockObject $goClient;

    private LogFileReader&MockObject $phpReader;

    private LogSourceManager&MockObject $sourceManager;

    private TagAwareCacheInterface&MockObject $cache;

    private CompositeLogParser&MockObject $compositeLogParser;

    protected function setUp(): void
    {
        $configProvider = $this->createConfigurationProvider();

        $this->goClient = $this->createMock(GoLogClient::class);
        $this->phpReader = $this->createMock(LogFileReader::class);
        $this->sourceManager = $this->createMock(LogSourceManager::class);
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->compositeLogParser = $this->createMock(CompositeLogParser::class);

        $this->viewer = new LogViewer(
            configurationProvider: $configProvider,
            goClient: $this->goClient,
            phpReader: $this->phpReader,
            sourceManager: $this->sourceManager,
            cache: $this->cache,
            compositeLogParser: $this->compositeLogParser
        );
    }

    /**
     * @param class-string $parserClass
     */
    #[DataProvider('getEntriesCustomParserDataProvider')]
    public function testGetEntriesCustomParser(
        bool $parserGoEnabled,
        string $parserClass,
        string $parserType,
        bool $isGoEnabledByParser,
        bool $expectGoClientCall
    ): void {
        $filePath = __DIR__ . '/../../../Mock/Log/custom.log';
        $this->setUseGo($parserGoEnabled);

        $customParser = $this->createMock($parserClass);

        $this->compositeLogParser
            ->expects($expectGoClientCall ? $this->once() : $this->any())
            ->method('getParser')
            ->with($parserType)
            ->willReturn($customParser);

        $this->compositeLogParser
            ->expects($this->any())
            ->method('isGoParserEnabled')
            ->with($customParser)
            ->willReturn($isGoEnabledByParser);

        if ($expectGoClientCall) {
            $this->goClient
                ->expects($this->once())
                ->method('getLogs')
                ->willReturn([]);

            $this->phpReader
                ->expects($this->never())
                ->method('getEntries');
        } else {
            $this->phpReader
                ->expects($this->once())
                ->method('getEntries')
                ->willReturn([]);

            $this->goClient
                ->expects($this->never())
                ->method('getLogs');
        }

        $this->viewer->getEntries(
            filePath: $filePath,
            parserType: $parserType
        );
    }

    public function testGetSources(): void
    {
        $logViewerSource = new LogViewerSource(
            id: 'test_id',
            name: 'test_name',
            path: '/path/to/log',
            host: null,
            parserType: 'monolog',
            isValid: true,
            isEmpty: false,
            isTooLarge: false,
            canDelete: false,
            isDeletable: false,
            canDownload: false,
            isDownloadable: false,
            size: 1_024,
            modified: '2026-03-31T20:45:00+00:00'
        );
        $expectedSources = [$logViewerSource];

        $this->sourceManager
            ->expects($this->once())
            ->method('getAllSources')
            ->willReturn($expectedSources);

        $sources = $this->viewer->getSources();

        $this->assertSame($expectedSources, $sources);
    }

    /**
     * @param LogViewerFilters $filters
     * @param LogEntry[] $expectedEntries
     */
    #[DataProvider('getEntriesDataProvider')]
    public function testGetEntries(
        bool $useGo,
        string $filePath,
        string $parserType,
        LogViewerFilters $filters,
        int $limit,
        ?string $cursor,
        string $sortDir,
        int $offset,
        array $expectedEntries
    ): void {
        $this->setUseGo($useGo);

        if ($useGo) {
            $this->goClient
                ->expects($this->once())
                ->method('getLogs')
                ->with($filePath, $parserType, $filters, $limit, $cursor, $sortDir, $offset)
                ->willReturn($expectedEntries);

            $this->phpReader
                ->expects($this->never())
                ->method('getEntries');
        } else {
            $this->phpReader
                ->expects($this->once())
                ->method('getEntries')
                ->with($filePath, $parserType, $limit, $cursor, $filters, $sortDir, $offset, null)
                ->willReturn($expectedEntries);

            $this->goClient
                ->expects($this->never())
                ->method('getLogs');
        }

        $entries = $this->viewer->getEntries(
            filePath: $filePath,
            parserType: $parserType,
            filters: $filters,
            limit: $limit,
            cursor: $cursor,
            sortDir: $sortDir,
            offset: $offset
        );

        $this->assertSame($expectedEntries, $entries);
    }

    /**
     * @param LogViewerFilters $filters
     */
    #[DataProvider('getStatsDataProvider')]
    public function testGetStats(
        bool $useGo,
        string $filePath,
        string $parserType,
        LogViewerFilters $filters,
        LogViewerStats $expectedStats
    ): void {
        $this->setUseGo($useGo);

        if ($useGo) {
            $this->goClient
                ->expects($this->once())
                ->method('getStats')
                ->with($filePath, $parserType, $filters)
                ->willReturn($expectedStats);

            $this->phpReader
                ->expects($this->never())
                ->method('getStats');
        } else {
            $this->phpReader
                ->expects($this->once())
                ->method('getStats')
                ->with($filePath, $parserType, $filters, null)
                ->willReturn($expectedStats);

            $this->goClient
                ->expects($this->never())
                ->method('getStats');
        }

        $stats = $this->viewer->getStats($filePath, $parserType, $filters);

        $this->assertSame($expectedStats, $stats);
    }

    public function testGetStatsUsesCache(): void
    {
        $configProvider = $this->createConfigurationProvider(cacheStatisticEnabled: true);

        $viewer = new LogViewer(
            configurationProvider: $configProvider,
            goClient: $this->goClient,
            phpReader: $this->phpReader,
            sourceManager: $this->sourceManager,
            cache: $this->cache,
            compositeLogParser: $this->compositeLogParser
        );

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(new LogViewerStats(100, '2026-01-01', '2026-01-01'));

        $stats = $viewer->getStats('/path/to/log', 'monolog');
        $this->assertSame(100, $stats->size);
    }

    public function testGetStatsSkipsCacheIfFiltersPresent(): void
    {
        $configProvider = $this->createConfigurationProvider(cacheStatisticEnabled: true);

        $viewer = new LogViewer(
            configurationProvider: $configProvider,
            goClient: $this->goClient,
            phpReader: $this->phpReader,
            sourceManager: $this->sourceManager,
            cache: $this->cache,
            compositeLogParser: $this->compositeLogParser
        );

        $expectedStats = new LogViewerStats(10, '2026-01-01', '2026-01-01');
        $this->phpReader
            ->expects($this->once())
            ->method('getStats')
            ->willReturn($expectedStats);

        $this->cache->expects($this->never())->method('get');

        $stats = $viewer->getStats('/path/to/log', 'monolog', LogViewerFilters::fromQueryParams(null, null, 'search'));
        $this->assertSame($expectedStats, $stats);
    }

    public static function getEntriesDataProvider(): Generator
    {
        yield [
            true,
            '/path/to/log',
            'monolog',
            LogViewerFilters::fromQueryParams('error', null, null),
            10,
            'cursor_value',
            'asc',
            5,
            [
                new LogEntry(
                    timestamp: '2026-03-31T20:45:00+00:00',
                    level: 'ERROR',
                    channel: 'app',
                    message: 'test error',
                    file: '',
                    normalizedTimestamp: '2026-03-31 20:45:00'
                )
            ]
        ];

        yield [
            false,
            '/path/to/log',
            'monolog',
            LogViewerFilters::fromQueryParams('info', null, null),
            20,
            null,
            'desc',
            0,
            [
                new LogEntry(
                    timestamp: '2026-03-31T20:45:00+00:00',
                    level: 'INFO',
                    channel: 'app',
                    message: 'test info',
                    file: '',
                    normalizedTimestamp: '2026-03-31 20:45:00'
                )
            ]
        ];
    }

    public static function getEntriesCustomParserDataProvider(): Generator
    {
        yield 'CustomNoGo + GoEnabled' => [
            true,
            CustomNoGoParser::class,
            'custom_no_go',
            false,
            false
        ];

        yield 'CustomNoGo + GoDisabled' => [
            false,
            CustomNoGoParser::class,
            'custom_no_go',
            false,
            false
        ];

        yield 'CustomGo + GoEnabled' => [
            true,
            CustomGoParser::class,
            'custom_go',
            true,
            true
        ];

        yield 'CustomGo + GoDisabled' => [
            false,
            CustomGoParser::class,
            'custom_go',
            true,
            false
        ];
    }

    public static function getStatsDataProvider(): Generator
    {
        yield [
            true,
            '/path/to/log',
            'monolog',
            LogViewerFilters::fromQueryParams(null, null, null),
            LogViewerStats::fromArray([
                'total' => 100,
                'size' => 5_000,
                'levels' => ['INFO' => 80, 'ERROR' => 20],
                'channels' => ['app' => 100],
                'timeline' => ['2026-03-31' => 100],
                'updatedAt' => '2026-03-31T20:45:00+00:00'
            ])
        ];

        yield [
            false,
            '/path/to/log',
            'doctrine',
            LogViewerFilters::fromQueryParams(null, 'db', null),
            LogViewerStats::fromArray([
                'total' => 50,
                'size' => 2_000,
                'levels' => ['DEBUG' => 50],
                'channels' => ['db' => 50],
                'timeline' => ['2026-03-31' => 50],
                'updatedAt' => '2026-03-31T20:45:00+00:00'
            ])
        ];
    }

    private function setUseGo(bool $useGo): void
    {
        $configProvider = $this->createConfigurationProvider(parserGoEnabled: $useGo);

        $this->viewer = new LogViewer(
            configurationProvider: $configProvider,
            goClient: $this->goClient,
            phpReader: $this->phpReader,
            sourceManager: $this->sourceManager,
            cache: $this->cache,
            compositeLogParser: $this->compositeLogParser
        );
    }

    private function createConfigurationProvider(
        bool $parserGoEnabled = false,
        bool $cacheStatisticEnabled = false
    ): ConfigurationProvider {
        return new ConfigurationProvider(
            sourceDirs: [],
            sourceFiles: [],
            sourceIgnore: [],
            sourceMaxFileSize: null,
            sourceAllowDelete: false,
            sourceAllowDownload: false,
            parserDefault: 'monolog',
            parserOverrides: [],
            parserGoEnabled: $parserGoEnabled,
            parserGoBinaryPath: '',
            cacheParserDetectEnabled: false,
            cacheStatisticEnabled: $cacheStatisticEnabled,
            cacheStatisticInterval: 5,
            dashboardPageStatisticEnabled: true,
            dashboardPageAutoRefreshEnabled: false,
            dashboardPageAutoRefreshInterval: 5,
            dashboardPageAutoRefreshShowCountdown: false,
            liveLogPageEnabled: false,
            liveLogPageInterval: 5,
            logPageStatisticEnabled: true,
            logPageAutoRefreshEnabled: false,
            logPageAutoRefreshInterval: 5,
            logPageAutoRefreshShowCountdown: false,
            logPageLimit: 50,
            aiButtonLevels: ['error', 'critical', 'alert', 'emergency'],
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: false,
            notifierRules: []
        );
    }

    public function testSaveAndLoadWatcherPositions(): void
    {
        $positions = ['source1' => 100, 'source2' => 200];
        $userIdentifier = 'user123';
        $cacheKey = 'danilovl.log_viewer.watcher_positions.' . sha1($userIdentifier);

        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->with($cacheKey)
            ->willReturnCallback(static function (string $key, ?callable $callback = null) use ($positions) {
                return $positions;
            });

        $this->viewer->saveWatcherPositions($positions, $userIdentifier);
        $loadedPositions = $this->viewer->loadWatcherPositions($userIdentifier);

        $this->assertSame($positions, $loadedPositions);
    }
}
