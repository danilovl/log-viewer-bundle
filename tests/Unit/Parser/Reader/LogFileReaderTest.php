<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogFileReader

};
use PHPUnit\Framework\TestCase;

final class LogFileReaderTest extends TestCase
{
    use LogPathTrait;

    private LogFileReader $reader;

    private string $logPath;

    protected function setUp(): void
    {
        $parsers = [new MonologLineParser];
        $compositeParser = new CompositeLogParser($parsers);

        $this->reader = new LogFileReader($compositeParser);

        $this->logPath = $this->getLogPath('monolog.log');
    }

    public function testGetEntries(): void
    {
        $entries = $this->reader->getEntries($this->logPath, 'monolog', 3);

        $this->assertCount(3, $entries);
        $this->assertSame('', $entries[0]->timestamp);
        $this->assertSame('2026-03-29T09:44:16.945778+00:00', $entries[1]->timestamp);
        $this->assertSame('2026-03-29T09:44:15.945778+00:00', $entries[2]->timestamp);
    }

    public function testGetEntriesWithFilters(): void
    {
        $filters = LogViewerFilters::fromQueryParams('ERROR', null, null);
        $entries = $this->reader->getEntries($this->logPath, 'monolog', 10, null, $filters);

        $this->assertCount(1, $entries);
        $this->assertSame('ERROR', $entries[0]->level);
    }

    public function testGetStats(): void
    {
        $stats = $this->reader->getStats($this->logPath, 'monolog');

        $this->assertSame(4, $stats->total);
        $this->assertArrayHasKey('INFO', $stats->levels);
        $this->assertArrayHasKey('ERROR', $stats->levels);
        $this->assertArrayHasKey('CRITICAL', $stats->levels);
        $this->assertSame(2, $stats->levels['INFO']);
    }

    public function testGetCountNoFilters(): void
    {
        $count = $this->reader->getCount($this->logPath, 'monolog');
        $this->assertSame(4, $count);
    }

    public function testGetCountWithFilters(): void
    {
        $filters = LogViewerFilters::fromQueryParams('ERROR', null, null);
        $count = $this->reader->getCount($this->logPath, 'monolog', $filters);
        $this->assertSame(1, $count);
    }

    public function testGetNewEntriesFirstRequest(): void
    {
        $result = $this->reader->getNewEntries($this->logPath, 'monolog', 0);
        $this->assertCount(0, $result['entries']);
        $this->assertGreaterThan(0, $result['position']);
        $this->assertSame((int) filesize($this->logPath), $result['position']);
    }

    public function testGetNewEntriesSubsequentRequest(): void
    {
        $fileSize = (int) filesize($this->logPath);
        $halfSize = (int) ($fileSize / 2);

        $result = $this->reader->getNewEntries($this->logPath, 'monolog', $halfSize);
        $this->assertGreaterThan(0, count($result['entries']));
        $this->assertSame($fileSize, $result['position']);
    }
}
