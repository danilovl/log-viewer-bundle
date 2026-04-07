<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Go;

use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GoParserSortTest extends TestCase
{
    use LogPathTrait;

    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = $this->getGoBinaryPath();
    }

    #[DataProvider('provideSortCases')]
    public function testSort(string $logFile, string $parser, string $sort, string $expectedFirstTimestamp): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--sort', $sort,
            '--limit', '1'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $this->assertNotEmpty($output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame($expectedFirstTimestamp, $decoded['timestamp']);
    }

    #[DataProvider('provideLimitOffsetCases')]
    public function testLimitOffset(string $logFile, string $parser, int $limit, int $offset, int $expectedCount): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--parser', $parser,
            '--limit', (string) $limit,
            '--offset', (string) $offset,
            '--sort', 'asc'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $lines = empty($output) ? [] : explode("\n", $output);

        $this->assertCount($expectedCount, $lines);
    }

    public static function provideSortCases(): Generator
    {
        yield ['monolog.log', 'monolog', 'asc', '2026-03-29T09:44:14.945778+00:00'];
        yield ['monolog.log', 'monolog', 'desc', '2026-03-29T09:44:16.945778+00:00'];
        yield ['json.log', 'json', 'asc', '2026-03-29T10:00:00+00:00'];
        yield ['json.log', 'json', 'desc', '2026-03-29T10:01:00+00:00'];
    }

    public static function provideLimitOffsetCases(): Generator
    {
        yield ['monolog.log', 'monolog', 1, 0, 1];
        yield ['monolog.log', 'monolog', 2, 0, 2];
        yield ['monolog.log', 'monolog', 1, 1, 1];
        yield ['monolog.log', 'monolog', 10, 0, 3];
        yield ['monolog.log', 'monolog', 10, 2, 1];
        yield ['monolog.log', 'monolog', 10, 3, 0];
    }

    public static function provideCursorCases(): Generator
    {
        // Cursor points to an entry, desc mode should return entries OLDER than cursor
        // monolog has timestamps: 14, 15, 16
        // Go-parser uses RFC3339 without timezone or with +00:00 for matching depending on input
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:16', 2];
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:15', 1];
        yield ['monolog.log', 'monolog', '2026-03-29 09:44:14', 0];
    }
}
