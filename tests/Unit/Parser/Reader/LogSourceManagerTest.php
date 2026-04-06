<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\DTO\RemoteHost;
use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use PHPUnit\Framework\Attributes\{
    AllowMockObjectsWithoutExpectations,
    DataProvider
};
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Generator;

#[AllowMockObjectsWithoutExpectations]
final class LogSourceManagerTest extends TestCase
{
    use LogPathTrait;

    private LogSourceManager $manager;

    private CompositeLogParser $compositeLogParser;

    protected function setUp(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $logDir = $this->getMockDir();

        $configProvider = $this->createConfigurationProvider(
            sourceDirs: [$logDir],
            sourceFiles: [$logPath]
        );

        $this->compositeLogParser = new CompositeLogParser([new MonologLineParser]);

        $this->manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $this->createMock(TagAwareCacheInterface::class)
        );
    }

    #[DataProvider('sourceFileDataProvider')]
    public function testSourceFileProperties(
        string $fileName,
        string $content,
        bool $expectedIsValid,
        bool $expectedIsEmpty,
        bool $expectedIsTooLarge,
        ?int $maxFileSize = null,
        ?string $parserDefault = 'monolog'
    ): void {
        $tempFile = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($tempFile, $content);

        try {
            $configProvider = $this->createConfigurationProvider(
                sourceFiles: [$tempFile],
                sourceMaxFileSize: $maxFileSize,
                parserDefault: $parserDefault
            );

            $manager = new LogSourceManager(
                configurationProvider: $configProvider,
                compositeLogParser: $this->compositeLogParser,
                eventDispatcher: $this->createMock(EventDispatcherInterface::class),
                cache: $this->createMock(TagAwareCacheInterface::class)
            );

            $sources = $manager->getAllSources();
            $this->assertCount(1, $sources);
            $source = $sources[0];

            $this->assertSame($expectedIsValid, $source->isValid);
            $this->assertSame($expectedIsEmpty, $source->isEmpty);
            $this->assertSame($expectedIsTooLarge, $source->isTooLarge);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testGetAllSources(): void
    {
        $sources = $this->manager->getAllSources();

        $this->assertNotEmpty($sources);

        $hasMonologLog = false;
        foreach ($sources as $source) {
            if (str_ends_with($source->path, 'monolog.log')) {
                $hasMonologLog = true;

                break;
            }
        }
        $this->assertTrue($hasMonologLog);
    }

    public function testGetFolders(): void
    {
        $folders = $this->manager->getFolders();

        $this->assertCount(1, $folders);
        $this->assertSame('Mock', $folders[0]->name);
    }

    public function testGetSourceById(): void
    {
        $sources = $this->manager->getAllSources();
        $sourceId = $sources[0]->id;

        $source = $this->manager->getSourceById($sourceId);

        $this->assertNotNull($source);
        $this->assertSame($sourceId, $source->id);
    }

    public function testGetSourcesSortOrder(): void
    {
        $logDir = sys_get_temp_dir() . '/log_sort_test_' . uniqid();
        mkdir($logDir);

        $fileB = $logDir . '/b.log';
        $fileA = $logDir . '/a.log';
        $fileC = $logDir . '/c.log';

        file_put_contents($fileB, 'content');
        sleep(1);
        file_put_contents($fileA, 'content');
        sleep(1);
        file_put_contents($fileC, 'content');

        $fileZ = sys_get_temp_dir() . '/z_individual.log';
        file_put_contents($fileZ, 'content');

        try {
            $configProvider = $this->createConfigurationProvider(
                sourceDirs: [$logDir],
                sourceFiles: [$fileZ]
            );

            $manager = new LogSourceManager(
                configurationProvider: $configProvider,
                compositeLogParser: $this->compositeLogParser,
                eventDispatcher: $this->createMock(EventDispatcherInterface::class),
                cache: $this->createMock(TagAwareCacheInterface::class)
            );

            $sources = $manager->getAllSources();
            
            $names = array_map(static fn ($s) => $s->name, $sources);
            
            $this->assertSame(['a.log', 'b.log', 'c.log', 'z_individual.log'], $names);
        } finally {
            unlink($fileA);
            unlink($fileB);
            unlink($fileC);
            rmdir($logDir);
            if (file_exists($fileZ)) {
                unlink($fileZ);
            }
        }
    }

    public function testDeleteFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/to_delete.log';
        file_put_contents($tempFile, 'data');

        $configProvider = $this->createConfigurationProvider(
            sourceFiles: [$tempFile],
            sourceAllowDelete: true,
            parserDefault: null
        );

        $manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $this->createMock(TagAwareCacheInterface::class)
        );
        $isDeleted = $manager->deleteFile($tempFile);

        $this->assertTrue($isDeleted);
        $this->assertFileDoesNotExist($tempFile);
    }

    public function testDeleteFileDisabled(): void
    {
        $tempFile = sys_get_temp_dir() . '/no_delete.log';
        file_put_contents($tempFile, 'data');

        $isDeleted = $this->manager->deleteFile($tempFile);

        try {
            $this->assertFalse($isDeleted);
            $this->assertFileExists($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testGetSourcesUsesCacheForParserDetection(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);

        $logPath = $this->getLogPath('monolog.log');
        $configProvider = $this->createConfigurationProvider(
            sourceFiles: [$logPath],
            parserDefault: null,
            cacheParserDetectEnabled: true,
            dashboardPageStatisticEnabled: false,
            logPageStatisticEnabled: false
        );

        $cache->expects($this->once())
            ->method('get')
            ->willReturn('cached_parser');

        $manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $cache
        );
        $sources = $manager->getAllSources();

        $this->assertSame('cached_parser', $sources[0]->parserType);
    }

    public function testGetFolderStructureDeduplication(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $logDir = $this->getMockDir();

        $configProvider = $this->createConfigurationProvider(
            sourceDirs: [$logDir],
            sourceFiles: [$logPath]
        );

        $manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $this->createMock(TagAwareCacheInterface::class)
        );

        $structure = $manager->getFolderStructure();
        $allFiles = [];
        foreach ($structure as $node) {
            $allFiles = array_merge($allFiles, $node->getAllFiles());
        }

        $monologFiles = array_filter($allFiles, static fn ($f) => str_ends_with($f->path, 'monolog.log'));

        $this->assertCount(1, $monologFiles);
        $this->assertSame('Individual files', $structure[0]->name);
    }

    public function testGetFolderStructureWithRemoteHosts(): void
    {
        $remoteHost = RemoteHost::fromArray([
            'name' => 'remote-1',
            'type' => 'ssh',
            'host' => '127.0.0.1',
            'port' => 22,
            'dirs' => [],
            'files' => ['/var/log/remote.log'],
            'ignore' => []
        ]);

        $configProvider = $this->createConfigurationProvider(
            sourceRemoteHosts: [$remoteHost]
        );

        $manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $this->createMock(TagAwareCacheInterface::class)
        );

        $structure = $manager->getFolderStructure();

        $this->assertCount(1, $structure);
        $this->assertSame('remote-1 (Remote)', $structure[0]->name);
        $this->assertCount(1, $structure[0]->files);
        $this->assertSame('/var/log/remote.log', $structure[0]->files[0]->path);
        $this->assertSame('remote-1', $structure[0]->files[0]->host);
    }

    public function testGetFolderStructureWithIgnoredFiles(): void
    {
        $logDir = $this->getMockDir();
        $ignoredFile = 'monolog.log';

        $configProvider = $this->createConfigurationProvider(
            sourceDirs: [$logDir],
            sourceIgnore: [$ignoredFile]
        );

        $manager = new LogSourceManager(
            configurationProvider: $configProvider,
            compositeLogParser: $this->compositeLogParser,
            eventDispatcher: $this->createMock(EventDispatcherInterface::class),
            cache: $this->createMock(TagAwareCacheInterface::class)
        );

        $sources = $manager->getAllSources();
        foreach ($sources as $source) {
            $this->assertStringNotContainsString($ignoredFile, $source->path);
        }
    }

    public static function sourceFileDataProvider(): Generator
    {
        yield 'empty_file' => [
            'empty.log',
            '',
            true,
            true,
            false,
            null,
            null
        ];

        yield 'normal_file' => [
            'normal.log',
            '[2023-01-01 00:00:00] app.INFO: message [] []',
            true,
            false,
            false,
            null,
            'monolog'
        ];

        yield 'too_large_file' => [
            'large.log',
            'some content that is longer than 5 bytes',
            false,
            false,
            true,
            5,
            null
        ];

        yield 'invalid_parser_file' => [
            'invalid_parser.log',
            'random content without known format',
            false,
            false,
            false,
            null,
            null
        ];
    }

    /**
     * @param string[] $sourceDirs
     * @param string[] $sourceFiles
     * @param string[] $sourceIgnore
     * @param array<string, string> $parserOverrides
     * @param RemoteHost[] $sourceRemoteHosts
     */
    private function createConfigurationProvider(
        array $sourceDirs = [],
        array $sourceFiles = [],
        array $sourceIgnore = [],
        ?int $sourceMaxFileSize = null,
        bool $sourceAllowDelete = false,
        bool $sourceAllowDownload = false,
        ?string $parserDefault = 'monolog',
        array $parserOverrides = [],
        bool $cacheParserDetectEnabled = false,
        bool $dashboardPageStatisticEnabled = true,
        bool $logPageStatisticEnabled = true,
        array $sourceRemoteHosts = []
    ): ConfigurationProvider {
        return new ConfigurationProvider(
            sourceDirs: $sourceDirs,
            sourceFiles: $sourceFiles,
            sourceIgnore: $sourceIgnore,
            sourceMaxFileSize: $sourceMaxFileSize,
            sourceAllowDelete: $sourceAllowDelete,
            sourceAllowDownload: $sourceAllowDownload,
            parserDefault: $parserDefault,
            parserOverrides: $parserOverrides,
            parserGoEnabled: false,
            parserGoBinaryPath: '',
            cacheParserDetectEnabled: $cacheParserDetectEnabled,
            cacheStatisticEnabled: true,
            cacheStatisticInterval: 5,
            dashboardPageStatisticEnabled: $dashboardPageStatisticEnabled,
            dashboardPageAutoRefreshEnabled: false,
            dashboardPageAutoRefreshInterval: 5,
            dashboardPageAutoRefreshShowCountdown: false,
            liveLogPageEnabled: false,
            liveLogPageInterval: 5,
            logPageStatisticEnabled: $logPageStatisticEnabled,
            logPageAutoRefreshEnabled: false,
            logPageAutoRefreshInterval: 5,
            logPageAutoRefreshShowCountdown: false,
            logPageLimit: 50,
            aiButtonLevels: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: $sourceRemoteHosts,
            notifierEnabled: false,
            notifierRules: []
        );
    }
}
