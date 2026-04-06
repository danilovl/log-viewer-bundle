<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Util;

use Danilovl\LogViewerBundle\Util\IntervalParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntervalParserTest extends TestCase
{
    #[DataProvider('provideParseCases')]
    public function testParse(string $interval, int $expected): void
    {
        $this->assertSame($expected, IntervalParser::parse($interval));
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public static function provideParseCases(): array
    {
        return [
            ['10 ms', 1],
            ['500 ms', 1],
            ['1000 ms', 1],
            ['1500 ms', 1],
            ['2000 ms', 2],
            ['5s', 5],
            ['5 s', 5],
            ['1m', 60],
            ['1 m', 60],
            ['1h', 3_600],
            ['1 h', 3_600],
            ['1d', 86_400],
            ['1 d', 86_400],
            ['5 seconds', 5],
            ['1 minute', 60],
            ['invalid', 5],
            ['-1 minute', 5],
        ];
    }
}
