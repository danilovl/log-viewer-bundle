<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PermissionDeniedTest extends TestCase
{
    public function testGetSourcesWithUnreadableDir(): void
    {
        $unreadableDir = sys_get_temp_dir() . '/unreadable_log_dir_' . uniqid();
        mkdir($unreadableDir, 0o333);

        if (is_readable($unreadableDir)) {
            $this->markTestSkipped('Cannot create unreadable directory (possibly running as root or on Windows).');
        }

        try {
            $configProvider = new ConfigurationProvider(
                sourceDirs: [$unreadableDir],
                sourceFiles: [],
                sourceIgnore: [],
                sourceMaxFileSize: null,
                sourceAllowDelete: false,
                sourceAllowDownload: false,
                parserDefault: null,
                parserOverrides: [],
                parserGoEnabled: false,
                parserGoBinaryPath: '',
                cacheParserDetectEnabled: false,
                cacheStatisticEnabled: false,
                cacheStatisticInterval: 0,
                dashboardPageStatisticEnabled: false,
                dashboardPageAutoRefreshEnabled: false,
                dashboardPageAutoRefreshInterval: 0,
                dashboardPageAutoRefreshShowCountdown: false,
                liveLogPageEnabled: false,
                liveLogPageInterval: 0,
                logPageStatisticEnabled: false,
                logPageAutoRefreshEnabled: false,
                logPageAutoRefreshInterval: 0,
                logPageAutoRefreshShowCountdown: false,
                logPageLimit: 100,
                aiButtonLevels: [],
                aiChats: [],
                apiPrefix: '',
                encoreBuildName: null,
                sourceRemoteHosts: [],
                notifierEnabled: false,
                notifierRules: []
            );

            $compositeLogParser = new CompositeLogParser([new MonologLineParser]);

            $manager = new LogSourceManager(
                configurationProvider: $configProvider,
                compositeLogParser: $compositeLogParser,
                eventDispatcher: $this->createStub(EventDispatcherInterface::class),
                cache: $this->createStub(TagAwareCacheInterface::class)
            );

            $sources = $manager->getAllSources();
            $this->assertCount(0, $sources);
        } finally {
            chmod($unreadableDir, 0o777);
            rmdir($unreadableDir);
        }
    }
}
