<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Util;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

final class DateNormalizerReproductionTest extends TestCase
{
    public function testFillTimelineGapsGaps(): void
    {
        $timeline = [
            '2026-03-27 10:00:00' => 5,
            '2026-03-29 12:00:00' => 10,
        ];

        $result = DateNormalizer::fillTimelineGaps($timeline, 'day');

        $this->assertCount(3, $result, 'Should have 3 days: 27, 28, 29. Result keys: ' . implode(', ', array_keys($result)));
        $this->assertEquals(5, $result['2026-03-27 00:00:00']);
        $this->assertEquals(0, $result['2026-03-28 00:00:00']);
        $this->assertEquals(10, $result['2026-03-29 00:00:00']);
    }

    public function testFillTimelineGapsSingleEntry(): void
    {
        $timeline = [
            '2026-04-03 00:00:00' => 1,
        ];

        $result = DateNormalizer::fillTimelineGaps($timeline, 'day');
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('2026-04-03 00:00:00', $result);
    }

    public function testFillTimelineGapsWithEndRange(): void
    {
        $timeline = [
            '2026-04-01 10:00:00' => 1,
        ];

        $endRange = new DateTimeImmutable('2026-04-03 15:00:00');
        $result = DateNormalizer::fillTimelineGaps($timeline, 'day', null, $endRange);

        $this->assertCount(3, $result, 'Should have entries for April 1, 2, and 3');
        $this->assertArrayHasKey('2026-04-01 00:00:00', $result);
        $this->assertArrayHasKey('2026-04-02 00:00:00', $result);
        $this->assertArrayHasKey('2026-04-03 00:00:00', $result);
        $this->assertEquals(1, $result['2026-04-01 00:00:00']);
        $this->assertEquals(0, $result['2026-04-02 00:00:00']);
        $this->assertEquals(0, $result['2026-04-03 00:00:00']);
    }

    public function testFillTimelineGapsWithStartAndEndRange(): void
    {
        $timeline = [
            '2026-04-02 10:00:00' => 5,
        ];

        $startRange = new DateTimeImmutable('2026-04-01 00:00:00');
        $endRange = new DateTimeImmutable('2026-04-03 00:00:00');
        $result = DateNormalizer::fillTimelineGaps($timeline, 'day', $startRange, $endRange);

        $this->assertCount(3, $result);
        $this->assertEquals(0, $result['2026-04-01 00:00:00']);
        $this->assertEquals(5, $result['2026-04-02 00:00:00']);
        $this->assertEquals(0, $result['2026-04-03 00:00:00']);
    }
}
