<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\RemoteHost;
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use Danilovl\LogViewerBundle\Parser\Reader\GoLogArguments;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\TestCase;

final class GoLogArgumentsTest extends TestCase
{
    public function testPasswordIsPassedViaEnvironmentNotArguments(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote',
            type: 'ssh',
            host: '10.0.0.1',
            port: 22,
            dirs: [],
            files: [],
            user: 'admin',
            password: 'top-secret'
        );

        $arguments = $this->createArguments($remoteHost);
        $arguments->addHost('remote');

        $argumentList = $arguments->toArray();
        $environment = $arguments->getEnvironment();
        $expectedEnvironment = [GoLogArguments::ENV_PASSWORD => 'top-secret'];

        $this->assertNotContains('--password', $argumentList);
        $this->assertNotContains('top-secret', $argumentList);
        $this->assertSame($expectedEnvironment, $environment);

        $userIndex = array_search('--user', $argumentList, true);
        $this->assertIsInt($userIndex);
        $this->assertSame('admin', $argumentList[$userIndex + 1]);
    }

    public function testNoPasswordMeansEmptyEnvironment(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote',
            type: 'ssh',
            host: '10.0.0.1',
            port: 22,
            dirs: [],
            files: [],
            user: 'admin'
        );

        $arguments = $this->createArguments($remoteHost);
        $arguments->addHost('remote');
        $environment = $arguments->getEnvironment();

        $this->assertSame([], $environment);
    }

    public function testUnknownHostAddsNothing(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote',
            type: 'ssh',
            host: '10.0.0.1',
            port: 22,
            dirs: [],
            files: [],
            password: 'top-secret'
        );

        $arguments = $this->createArguments($remoteHost);

        $before = $arguments->toArray();
        $arguments->addHost('unknown');
        $after = $arguments->toArray();
        $environment = $arguments->getEnvironment();

        $this->assertSame($before, $after);
        $this->assertSame([], $environment);
    }

    private function createArguments(RemoteHost $remoteHost): GoLogArguments
    {
        $configurationProvider = new ConfigurationProvider(
            sourceDirs: [],
            sourceFiles: [],
            sourceIgnore: [],
            sourceMaxFileSize: null,
            sourceAllowDelete: false,
            sourceAllowDownload: false,
            parserDefault: null,
            parserOverrides: [],
            parserGoEnabled: true,
            parserGoBinaryPath: '/bin/go-parser',
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
            sourceRemoteHosts: [$remoteHost->name => $remoteHost],
            notifierEnabled: false,
            notifierRules: []
        );

        $compositeLogParser = new CompositeLogParser([]);

        return new GoLogArguments(
            binaryPath: '/bin/go-parser',
            filePath: '/var/log/app.log',
            configurationProvider: $configurationProvider,
            compositeLogParser: $compositeLogParser
        );
    }
}
