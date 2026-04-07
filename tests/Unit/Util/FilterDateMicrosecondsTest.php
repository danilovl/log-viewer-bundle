<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Util;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Parser\Reader\LogFileReader;
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class FilterDateMicrosecondsTest extends TestCase
{
    private LogFileReader $reader;

    protected function setUp(): void
    {
        $this->reader = new LogFileReader(new CompositeLogParser([]));
    }

    public function testApplyFiltersWithMicroseconds(): void
    {
        $method = new ReflectionMethod(LogFileReader::class, 'applyFilters');
        $method->setAccessible(true);

        // Entry with microseconds in original timestamp, but normalized to Y-m-d H:i:s
        $entry = new LogEntry(
            timestamp: '2026-04-06T15:54:08.940640+02:00',
            level: 'INFO',
            channel: 'app',
            message: 'test message',
            file: 'test.log',
            normalizedTimestamp: '2026-04-06 15:54:08',
            context: []
        );

        // Filter for exactly the same second
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: null,
            dateFrom: '2026-04-06 15:54:08',
            dateTo: '2026-04-06 15:54:08'
        );

        $result = $method->invoke($this->reader, $entry, $filters, null, 'desc');
        $this->assertTrue($result, 'Should match when filter second is same as entry second (ignoring microseconds)');

        // Filter starts just after the second
        $filtersAfter = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: null,
            dateFrom: '2026-04-06 15:54:09',
            dateTo: null
        );
        $resultAfter = $method->invoke($this->reader, $entry, $filtersAfter, null, 'desc');
        $this->assertFalse($resultAfter, 'Should NOT match when filter start is after entry second');

        // Filter ends just before the second
        $filtersBefore = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: null,
            dateFrom: null,
            dateTo: '2026-04-06 15:54:07'
        );
        $resultBefore = $method->invoke($this->reader, $entry, $filtersBefore, null, 'desc');
        $this->assertFalse($resultBefore, 'Should NOT match when filter end is before entry second');
    }
}
