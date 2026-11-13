<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\RemoteHost;
use Danilovl\LogViewerBundle\Parser\Reader\RemoteLogReader;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class RemoteLogReaderTest extends TestCase
{
    public function testHttpOpenFailureDoesNotLeakCredentials(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote-http',
            type: 'http',
            host: '127.0.0.1',
            port: 1,
            dirs: [],
            files: [],
            user: 'admin',
            password: 'top-secret'
        );

        $configurationProvider = $this->createConfigurationProvider($remoteHost);
        $reader = new RemoteLogReader($configurationProvider);

        try {
            $reader->openFile('remote-http', '/var/log/app.log');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('remote-http', $message);
            $this->assertStringContainsString('/var/log/app.log', $message);
            $this->assertStringNotContainsString('top-secret', $message);
            $this->assertStringNotContainsString('admin', $message);
        }
    }

    public function testBuildHttpUrlEncodesCredentials(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote-http',
            type: 'https',
            host: 'logs.example.com',
            port: 8_443,
            dirs: [],
            files: [],
            user: 'user@corp',
            password: 'p@ss:w/rd#1'
        );

        $url = $this->buildHttpUrl($remoteHost, '/app.log');

        $host = parse_url($url, PHP_URL_HOST);

        $this->assertSame('https://user%40corp:p%40ss%3Aw%2Frd%231@logs.example.com:8443/app.log', $url);
        $this->assertSame('logs.example.com', $host);
    }

    public function testBuildHttpUrlWithUserOnly(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote-http',
            type: 'http',
            host: 'logs.example.com',
            port: 80,
            dirs: [],
            files: [],
            user: 'user@corp'
        );

        $url = $this->buildHttpUrl($remoteHost, '/app.log');

        $this->assertSame('http://user%40corp@logs.example.com:80/app.log', $url);
    }

    public function testBuildHttpUrlWithoutCredentials(): void
    {
        $remoteHost = new RemoteHost(
            name: 'remote-http',
            type: 'http',
            host: 'logs.example.com',
            port: 80,
            dirs: [],
            files: []
        );

        $url = $this->buildHttpUrl($remoteHost, '/app.log');

        $this->assertSame('http://logs.example.com:80/app.log', $url);
    }

    private function buildHttpUrl(RemoteHost $remoteHost, string $path): string
    {
        $configurationProvider = $this->createConfigurationProvider($remoteHost);
        $reader = new RemoteLogReader($configurationProvider);
        $method = new ReflectionMethod(RemoteLogReader::class, 'buildHttpUrl');

        /** @var string $url */
        $url = $method->invoke($reader, $remoteHost, $path);

        return $url;
    }

    private function createConfigurationProvider(RemoteHost $remoteHost): ConfigurationProvider
    {
        return new ConfigurationProvider(
            sourceDirs: [],
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
    }
}
