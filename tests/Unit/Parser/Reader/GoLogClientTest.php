<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\Parser\Reader\GoLogClient;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoLogClientTest extends TestCase
{
    use LogPathTrait;

    private CompositeLogParser $compositeLogParser;

    protected function setUp(): void
    {
        $this->compositeLogParser = new CompositeLogParser([new MonologLineParser]);
    }

    public function testGetLogsBinaryNotFound(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $invalidConfig = $this->createConfigurationProvider(parserGoBinaryPath: '/invalid/path');
        $client = new GoLogClient($invalidConfig, $this->compositeLogParser);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Go parser binary not found');

        $client->getLogs($logPath, 'monolog');
    }

    public function testGetLogs(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $fakeBinary = $this->createFakeLogsBinary();

        try {
            $client = $this->createClientWithBinary($fakeBinary);
            $logs = $client->getLogs($logPath, 'monolog', limit: 1, sortDir: 'asc');

            $this->assertCount(1, $logs);
            $this->assertSame('2026-03-29T09:44:14.945778+00:00', $logs[0]->timestamp);
        } finally {
            @unlink($fakeBinary);
        }
    }

    public function testGetStatsUsesStatsModeWithoutFilters(): void
    {
        $binaryPath = $this->createFakeStatsBinary('stats');

        try {
            $client = $this->createClientWithBinary($binaryPath);
            $result = $client->getStats(__FILE__, 'monolog');

            $this->assertSame(0, $result->total);
        } finally {
            @unlink($binaryPath);
        }
    }

    public function testGetStatsUsesStatFilterModeWithFilters(): void
    {
        $binaryPath = $this->createFakeStatsBinary('stat_filter');

        try {
            $client = $this->createClientWithBinary($binaryPath);
            $result = $client->getStats(__FILE__, 'monolog', LogViewerFilters::fromQueryParams('ERROR', null, null));

            $this->assertSame(0, $result->total);
        } finally {
            @unlink($binaryPath);
        }
    }

    private function createFakeLogsBinary(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'go-parser-logs-');
        self::assertNotFalse($tempFile);

        $script = <<<'PHP'
#!/usr/bin/env php
<?php
$parser = '';
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--parser' && isset($argv[$i + 1])) {
        $parser = $argv[$i + 1];
        break;
    }
}

if ($parser !== 'monolog') {
    fwrite(STDERR, sprintf('unexpected parser: %s', $parser));
    exit(1);
}

echo '{"timestamp":"2026-03-29T09:44:14.945778+00:00","level":"DEBUG","channel":"app","message":"test message","file":"test.log","context":{},"extra":{},"offset":0,"line":1}';
PHP;

        $script = str_replace("\r\n", "\n", $script);
        file_put_contents($tempFile, $script);
        chmod($tempFile, 0o755);

        return $tempFile;
    }

    private function createClientWithBinary(string $binaryPath): GoLogClient
    {
        $config = $this->createConfigurationProvider(parserGoBinaryPath: $binaryPath);

        return new GoLogClient($config, $this->compositeLogParser);
    }

    private function createConfigurationProvider(
        string $parserGoBinaryPath = '',
        bool $parserGoEnabled = true
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
            parserGoBinaryPath: $parserGoBinaryPath,
            cacheParserDetectEnabled: false,
            cacheStatisticEnabled: false,
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
            aiButtonLevels: [],
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: false,
            notifierRules: []
        );
    }

    private function createFakeStatsBinary(string $expectedMode): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'go-parser-test-');
        self::assertNotFalse($tempFile);

        $script = sprintf(
            <<<'PHP'
#!/usr/bin/env php
<?php
$expectedMode = %s;
$mode = '';
$parser = '';

for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--mode' && isset($argv[$i + 1])) {
        $mode = $argv[$i + 1];
    }
    if ($argv[$i] === '--parser' && isset($argv[$i + 1])) {
        $parser = $argv[$i + 1];
    }
}

if ($mode !== $expectedMode) {
    fwrite(STDERR, sprintf('unexpected mode: %%s', $mode));
    exit(1);
}

if ($parser !== 'monolog') {
    fwrite(STDERR, sprintf('unexpected parser: %%s', $parser));
    exit(1);
}

echo '{"updated_at":"2026-01-01T00:00:00Z","size":1,"total":0,"levels":{},"channels":{},"timeline":{}}';
PHP,
            var_export($expectedMode, true)
        );

        $script = str_replace("\r\n", "\n", $script);
        file_put_contents($tempFile, $script);
        chmod($tempFile, 0o755);

        return $tempFile;
    }
}
