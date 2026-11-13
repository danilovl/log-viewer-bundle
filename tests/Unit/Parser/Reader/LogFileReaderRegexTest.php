<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    MonologLineParser
};
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Parser\Reader\LogFileReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Generator;

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
        $search = 'crit.*cal';
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: $search,
            searchRegex: true
        );

        $entries = $this->reader->getEntries($this->logPath, 'monolog', 10, null, $filters);

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

    #[DataProvider('provideInvalidRegexThrowsBadRequestCases')]
    public function testInvalidRegexThrowsBadRequest(string $search): void
    {
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: $search,
            searchRegex: true
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid search regex.');

        $this->reader->getEntries($this->logPath, 'monolog', 10, null, $filters);
    }

    public function testInvalidRegexThrowsBadRequestForCount(): void
    {
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: '(unclosed',
            searchRegex: true
        );

        $this->expectException(BadRequestHttpException::class);

        $this->reader->getCount($this->logPath, 'monolog', $filters);
    }

    public function testRegexWithDelimiterCharacters(): void
    {
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: 'crit~?ical|/never/',
            searchRegex: true
        );

        $entries = $this->reader->getEntries($this->logPath, 'monolog', 10, null, $filters);

        $this->assertCount(1, $entries);
        $this->assertSame('CRITICAL', $entries[0]->level);
    }

    public static function provideInvalidRegexThrowsBadRequestCases(): Generator
    {
        yield 'unclosed group' => ['(unclosed'];
        yield 'unclosed class' => ['[a-z'];
        yield 'nothing to repeat' => ['*error'];
        yield 'trailing backslash' => ['error\\'];
    }
}
