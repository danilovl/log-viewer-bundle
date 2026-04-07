<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Go;

use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GoParserFilterTest extends TestCase
{
    use LogPathTrait;

    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = $this->getGoBinaryPath();
    }

    #[DataProvider('provideLevelFilterCases')]
    public function testLevelFilter(string $logFile, string $parser, string $level, int $expectedCount): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--level', $level,
            '--limit', '100'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $lines = empty($output) ? [] : explode("\n", $output);

        $this->assertCount($expectedCount, $lines);
    }

    #[DataProvider('provideLevelsFilterCases')]
    public function testLevelsFilter(string $logFile, string $parser, string $levels, int $expectedCount): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--levels', $levels,
            '--limit', '100'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $lines = empty($output) ? [] : explode("\n", $output);

        $this->assertCount($expectedCount, $lines);
    }

    #[DataProvider('provideChannelFilterCases')]
    public function testChannelFilter(string $logFile, string $parser, string $channel, int $expectedCount): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--channel', $channel,
            '--limit', '100'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $lines = empty($output) ? [] : explode("\n", $output);

        $this->assertCount($expectedCount, $lines);
    }

    #[DataProvider('provideSearchFilterCases')]
    public function testSearchFilter(string $logFile, string $parser, string $search, int $expectedCount, bool $regex = false, bool $caseSensitive = false): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--search', $search,
            '--limit', '100'
        ];

        if ($regex) {
            $cmd[] = '--search-regex';
        }

        if ($caseSensitive) {
            $cmd[] = '--search-case-sensitive';
        }

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $lines = empty($output) ? [] : explode("\n", $output);

        $this->assertCount($expectedCount, $lines);
    }

    public static function provideLevelFilterCases(): Generator
    {
        yield ['monolog.log', 'monolog', 'INFO', 1];
        yield ['monolog.log', 'monolog', 'ERROR', 1];
        yield ['monolog.log', 'monolog', 'DEBUG', 0];
        yield ['json.log', 'json', 'INFO', 1];
        yield ['json.log', 'json', 'ERROR', 1];
    }

    public static function provideLevelsFilterCases(): Generator
    {
        yield ['monolog.log', 'monolog', 'INFO,ERROR', 2];
        yield ['monolog.log', 'monolog', 'CRITICAL,DEBUG', 1];
        yield ['json.log', 'json', 'INFO,ERROR', 2];
    }

    public static function provideChannelFilterCases(): Generator
    {
        yield ['monolog.log', 'monolog', 'app', 2];
        yield ['monolog.log', 'monolog', 'request', 1];
        yield ['json.log', 'json', 'app', 1];
        yield ['json.log', 'json', 'db', 1];
    }

    public static function provideSearchFilterCases(): Generator
    {
        yield ['monolog.log', 'monolog', 'test', 1];
        yield ['monolog.log', 'monolog', 'message', 3];
        yield ['monolog.log', 'monolog', 'TEST', 1]; // Case-insensitive by default
        yield ['monolog.log', 'monolog', 'TEST', 0, false, true]; // Case-sensitive
        yield ['monolog.log', 'monolog', 't.st', 1, true]; // Regex
        yield ['json.log', 'json', 'JSON', 2];
        yield ['json.log', 'json', 'error', 1];
    }
}
