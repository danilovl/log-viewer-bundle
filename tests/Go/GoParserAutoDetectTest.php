<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Go;

use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GoParserAutoDetectTest extends TestCase
{
    use LogPathTrait;

    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = $this->getGoBinaryPath();
    }

    #[DataProvider('provideAutoDetectCases')]
    public function testAutoDetect(string $logFile, string $expectedTimestamp): void
    {
        $logPath = $this->getLogPath($logFile);
        $cmd = [
            $this->binaryPath,
            '--file', $logPath,
            '--limit', '1',
            '--sort', 'asc'
        ];

        $process = new Process($cmd);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "Failed auto-detect for {$logFile}. Error: " . $process->getErrorOutput());

        $output = mb_trim($process->getOutput());
        $this->assertNotEmpty($output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame($expectedTimestamp, $decoded['timestamp']);
    }

    public static function provideAutoDetectCases(): Generator
    {
        yield ['monolog.log', '2026-03-29T09:44:14.945778+00:00'];
        yield ['json.log', '2026-03-29T10:00:00+00:00'];
        yield ['nginx_error.log', '2026/04/01 15:24:40'];
    }
}
