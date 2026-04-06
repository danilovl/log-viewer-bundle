<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Go;

use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GoParserTest extends TestCase
{
    use LogPathTrait;

    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = $this->getGoBinaryPath();
    }

    #[DataProvider('provideParserResponseCases')]
    public function testParserResponse(
        string $logFile,
        string $parserType,
        string $expectedTimestamp,
        string $expectedLevel,
        string $expectedChannel
    ): void {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--limit', '1',
            '--sort', 'desc',
            '--parser', $parserType
        ];

        $process = new Process($cmd);
        $process->run();

        $isSuccessful = $process->isSuccessful();

        $this->assertTrue(
            $isSuccessful,
            sprintf('Failed for %s (%s). Error: %s', $logFile, $parserType, $process->getErrorOutput())
        );

        $output = $process->getOutput();
        $this->assertNotEmpty($output);

        $decoded = json_decode(mb_trim($output), true);

        $this->assertIsArray($decoded);
        $this->assertSame($expectedTimestamp, $decoded['timestamp']);
        $this->assertSame($expectedLevel, $decoded['level']);
        $this->assertSame($expectedChannel, $decoded['channel']);
    }

    #[DataProvider('provideParserStatsResponseCases')]
    public function testParserStatsResponse(
        string $logFile,
        string $parserType,
        int $expectedTotal
    ): void {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--mode', 'stats',
            '--parser', $parserType
        ];

        $process = new Process($cmd);
        $process->run();

        $isSuccessful = $process->isSuccessful();

        $this->assertTrue(
            $isSuccessful,
            sprintf('Failed for %s (%s). Error: %s', $logFile, $parserType, $process->getErrorOutput())
        );

        $output = $process->getOutput();
        $this->assertNotEmpty($output);

        $decoded = json_decode(mb_trim($output), true);
        $this->assertIsArray($decoded);
        $this->assertSame($expectedTotal, $decoded['total']);
        $this->assertArrayHasKey('levels', $decoded);
    }

    /**
     * @return iterable<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function provideParserResponseCases(): iterable
    {
        yield ['monolog.log', 'monolog', '2026-03-29T09:44:16.945778+00:00', 'CRITICAL', 'request'];
        yield ['nginx_access.log', 'nginx_access', '29/Mar/2026:10:01:00 +0000', 'WARNING', 'access'];
        yield ['nginx_error.log', 'nginx_error', '2026/04/01 16:24:03', 'ERROR', 'nginx'];
        yield ['syslog.log', 'syslog', 'Oct 11 22:15:01', 'INFO', 'cron'];
        yield ['json.log', 'json', '2026-03-29T10:01:00+00:00', 'ERROR', 'db'];
        yield ['apache_access.log', 'apache_access', '29/Mar/2026:11:01:00 +0000', 'WARNING', 'apache'];
        yield ['mysql.log', 'mysql', '2020-08-06T14:25:03.109022Z', 'INFO', 'Server'];
        yield ['php_error.log', 'php_error', '2026-03-29T09:44:15.945778+00:00', 'WARNING', 'php'];
        yield ['supervisord.log', 'supervisord', '2026-03-28 21:01:15,244', 'INFO', 'supervisord'];
        yield ['php8.4-fpm.log', 'php_fpm', '2026-03-29T12:41:54.049653+02:00', 'INFO', 'kernel'];
        yield ['doctrine.log', 'monolog', '2026-03-29T09:44:15.945778+00:00', 'DEBUG', 'doctrine'];
    }

    /**
     * @return iterable<int, array{0: string, 1: string, 2: int}>
     */
    public static function provideParserStatsResponseCases(): iterable
    {
        yield ['monolog.log', 'monolog', 3];
        yield ['nginx_access.log', 'nginx_access', 2];
        yield ['nginx_error.log', 'nginx_error', 3];
        yield ['syslog.log', 'syslog', 2];
        yield ['json.log', 'json', 2];
        yield ['apache_access.log', 'apache_access', 2];
        yield ['mysql.log', 'mysql', 4];
        yield ['php_error.log', 'php_error', 2];
        yield ['supervisord.log', 'supervisord', 25];
        yield ['php8.4-fpm.log', 'php_fpm', 27];
        yield ['auth.log', 'syslog', 1];
        yield ['kern.log', 'syslog', 1];
        yield ['doctrine.log', 'monolog', 2];
        yield ['access.log', 'nginx_access', 27];
    }
}
