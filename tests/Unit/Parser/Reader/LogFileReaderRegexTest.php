<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Parser\Reader\LogFileReader;
use PHPUnit\Framework\TestCase;

final class LogFileReaderRegexTest extends TestCase
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

    public function testGetEntriesWithRegexFilter(): void
    {
        // Паттерн, который НЕ совпадает как подстрока с "critical message", но совпадает как regex
        $search = 'crit.*cal';
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: $search,
            searchRegex: true
        );

        $entries = $this->reader->getEntries($this->logPath, 'monolog', 10, null, $filters);

        // В текущей реализации mb_stripos("critical message", "crit.*cal") вернет false,
        // и строка будет отфильтрована ДО вызова applyFilters.
        // Ожидаем 1 запись, если баг будет исправлен.
        $this->assertCount(1, $entries, 'Regex search should find "critical message" with pattern "crit.*cal"');
        $this->assertSame('CRITICAL', $entries[0]->level);
    }

    public function testGetCountWithRegexFilter(): void
    {
        $search = 'error|critical';
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: $search,
            searchRegex: true
        );

        $count = $this->reader->getCount($this->logPath, 'monolog', $filters);

        $this->assertSame(2, $count, 'Regex search should find 2 entries (ERROR and CRITICAL)');
    }
}
