<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Service;

use Danilovl\LogViewerBundle\DTO\RemoteHost;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationProviderTest extends TestCase
{
    public function testRemoteHostsMapping(): void
    {
        $host1 = new RemoteHost(
            name: 'host1',
            type: 'ssh',
            host: '1.2.3.4',
            port: 2_222,
            dirs: ['/var/log'],
            files: ['/var/log/syslog'],
            user: 'admin',
            password: 'secret',
            sshKey: '/home/user/.ssh/id_rsa',
            maxFileSize: 100
        );

        $remoteHosts = ['host1' => $host1];

        $configProvider = new ConfigurationProvider(
            sourceDirs: [],
            sourceFiles: [],
            sourceIgnore: [],
            sourceMaxFileSize: 50,
            sourceAllowDelete: false,
            sourceAllowDownload: false,
            parserDefault: null,
            parserOverrides: [],
            parserGoEnabled: false,
            parserGoBinaryPath: '',
            cacheParserDetectEnabled: false,
            cacheStatisticEnabled: false,
            cacheStatisticInterval: 5,
            dashboardPageStatisticEnabled: false,
            dashboardPageAutoRefreshEnabled: false,
            dashboardPageAutoRefreshInterval: 5,
            dashboardPageAutoRefreshShowCountdown: false,
            liveLogPageEnabled: false,
            liveLogPageInterval: 5,
            logPageStatisticEnabled: false,
            logPageAutoRefreshEnabled: false,
            logPageAutoRefreshInterval: 5,
            logPageAutoRefreshShowCountdown: false,
            logPageLimit: 50,
            aiButtonLevels: [],
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: $remoteHosts,
            notifierEnabled: false,
            notifierRules: []
        );

        $this->assertCount(1, $configProvider->sourceRemoteHosts);
        $this->assertArrayHasKey('host1', $configProvider->sourceRemoteHosts);
        $this->assertSame(50, $configProvider->sourceMaxFileSize);

        $host = $configProvider->sourceRemoteHosts['host1'];

        $this->assertSame('ssh', $host->type);
        $this->assertSame('1.2.3.4', $host->host);
        $this->assertSame(2_222, $host->port);
        $this->assertSame('admin', $host->user);
        $this->assertSame('secret', $host->password);
        $this->assertSame('/home/user/.ssh/id_rsa', $host->sshKey);
        $this->assertSame(100, $host->maxFileSize);
        $this->assertSame(['/var/log'], $host->dirs);
        $this->assertSame(['/var/log/syslog'], $host->files);
    }
}
