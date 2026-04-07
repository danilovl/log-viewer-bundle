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

    #[DataProvider('provideParserStatsResponseCases')]
    public function testParserCountResponse(
        string $logFile,
        string $parserType,
        int $expectedTotal
    ): void {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--mode', 'count',
            '--parser', $parserType
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('Failed for %s (%s). Error: %s', $logFile, $parserType, $process->getErrorOutput())
        );

        $output = mb_trim($process->getOutput());
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame($expectedTotal, $decoded['total']);
    }

    #[DataProvider('provideParserDateFilterCases')]
    public function testParserDateFilter(
        string $logFile,
        string $parserType,
        string $dateFrom,
        string $dateTo,
        int $expectedCount
    ): void {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parserType,
            '--date-from', $dateFrom,
            '--date-to', $dateTo,
            '--limit', '100'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('Failed for %s (%s). Error: %s', $logFile, $parserType, $process->getErrorOutput())
        );

        $output = $process->getOutput();
        $lines = $output
                |> mb_trim(...)
                |> (static fn (string $x): array => explode("\n", $x))
                |> array_filter(...);

        $this->assertCount($expectedCount, $lines);
    }

    #[DataProvider('provideParserDateFilterCases')]
    public function testParserDateFilterStats(
        string $logFile,
        string $parserType,
        string $dateFrom,
        string $dateTo,
        int $expectedCount
    ): void {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parserType,
            '--date-from', $dateFrom,
            '--date-to', $dateTo,
            '--mode', 'stats'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('Failed for %s (%s). Error: %s', $logFile, $parserType, $process->getErrorOutput())
        );

        $output = $process->getOutput();
        $this->assertNotEmpty($output);

        $decoded = json_decode(mb_trim($output), true);
        $this->assertIsArray($decoded);

        $this->assertSame($expectedCount, $decoded['total'], sprintf('Total count mismatch for %s (%s)', $logFile, $parserType));
    }

    /**
     * @return iterable<int, array{0: string, 1: string, 2: string, 3: string, 4: int}>
     */
    public static function provideParserDateFilterCases(): iterable
    {
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:14', '2026-03-29 09:44:15', 2];
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:15', '2026-03-29 09:44:16', 2];
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:16', '2026-03-29 09:44:17', 1];
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:17', '2026-03-29 09:44:18', 0];

        yield ['nginx_error.log', 'nginx_error', '2026-04-01 15:24:39', '2026-04-01 15:24:41', 1];
        yield ['nginx_error.log', 'nginx_error', '2026-04-01 16:04:57', '2026-04-01 16:04:59', 1];
        yield ['nginx_error.log', 'nginx_error', '2026-04-01 16:24:02', '2026-04-01 16:24:04', 1];
        yield ['nginx_error.log', 'nginx_error', '2026-04-01 16:24:05', '2026-04-01 16:24:06', 0];

        yield ['nginx_access.log', 'nginx_access', '2026-03-29 10:00:00', '2026-03-29 10:00:01', 1];
        yield ['nginx_access.log', 'nginx_access', '2026-03-29 10:01:00', '2026-03-29 10:01:01', 1];
        yield ['nginx_access.log', 'nginx_access', '2026-03-29 10:02:00', '2026-03-29 10:02:01', 0];

        yield ['apache_access.log', 'apache_access', '2026-03-29 11:00:00', '2026-03-29 11:00:01', 1];
        yield ['apache_access.log', 'apache_access', '2026-03-29 11:01:00', '2026-03-29 11:01:01', 1];
        yield ['apache_access.log', 'apache_access', '2026-03-29 11:02:00', '2026-03-29 11:02:01', 0];

        yield ['syslog.log', 'syslog', '2026-10-11 22:14:14', '2026-10-11 22:14:16', 2];
        yield ['syslog.log', 'syslog', '2026-10-11 22:15:00', '2026-10-11 22:15:02', 2];
        yield ['syslog.log', 'syslog', '2026-10-11 22:16:00', '2026-10-11 22:16:02', 2];

        yield ['json.log', 'json', '2026-03-29 10:00:00', '2026-03-29 10:00:01', 1];
        yield ['json.log', 'json', '2026-03-29 10:01:00', '2026-03-29 10:01:01', 1];
        yield ['json.log', 'json', '2026-03-29 10:02:00', '2026-03-29 10:02:01', 0];

        yield ['mysql.log', 'mysql', '2020-08-06 14:25:02', '2020-08-06 14:25:03', 4];
        yield ['mysql.log', 'mysql', '2020-08-06 14:25:03', '2020-08-06 14:25:04', 1];
        yield ['mysql.log', 'mysql', '2020-08-06 14:25:04', '2020-08-06 14:25:05', 0];

        yield ['php_error.log', 'php_error', '2026-03-29 09:44:14', '2026-03-29 09:44:15', 2];
        yield ['php_error.log', 'php_error', '2026-03-29 09:44:15', '2026-03-29 09:44:16', 1];
        yield ['php_error.log', 'php_error', '2026-03-29 09:44:16', '2026-03-29 09:44:17', 0];

        yield ['supervisord.log', 'supervisord', '2026-03-28 21:01:14', '2026-03-28 21:01:16', 2];
        yield ['supervisord.log', 'supervisord', '2026-03-28 21:01:16', '2026-03-28 21:01:17', 0];

        yield ['php8.4-fpm.log', 'php_fpm', '2026-03-29 12:41:53', '2026-03-29 12:41:55', 14];
        yield ['php8.4-fpm.log', 'php_fpm', '2026-03-29 12:41:55', '2026-03-29 12:41:56', 0];

        yield ['auth.log', 'syslog', '2026-03-29 00:05:43', '2026-03-29 00:05:44', 3];
        yield ['auth.log', 'syslog', '2026-03-29 10:17:01', '2026-03-29 10:17:02', 2];
        yield ['kern.log', 'syslog', '2026-03-29 00:59:20', '2026-03-29 00:59:21', 6];

        yield ['doctrine.log', 'monolog', '2026-03-29 09:44:14', '2026-03-29 09:44:15', 2];
        yield ['doctrine.log', 'monolog', '2026-03-29 09:44:15', '2026-03-29 09:44:16', 1];

        yield ['access.log', 'nginx_access', '2026-04-01 00:01:20', '2026-04-01 00:01:21', 5];
        yield ['access.log', 'nginx_access', '2026-04-01 00:04:51', '2026-04-01 00:04:52', 5];
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
        yield ['auth.log', 'syslog', '2026-03-29T10:39:01.021648+02:00', 'INFO', 'CRON'];
        yield ['kern.log', 'syslog', '2026-03-29T12:41:53.954399+02:00', 'INFO', 'kernel'];
        yield ['access.log', 'nginx_access', '01/Apr/2026:00:05:22 +0200', 'INFO', 'access'];
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
        yield ['auth.log', 'syslog', 16];
        yield ['kern.log', 'syslog', 17];
        yield ['doctrine.log', 'monolog', 2];
        yield ['access.log', 'nginx_access', 27];
    }
}
