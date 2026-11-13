<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\DTO;

use Danilovl\LogViewerBundle\DTO\LogViewerQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Generator;

final class LogViewerQueryTest extends TestCase
{
    #[DataProvider('limitDataProvider')]
    public function testGetSafeLimit(int $limit, int $expected): void
    {
        $query = new LogViewerQuery(sourceId: 'source', limit: $limit);

        $this->assertSame($expected, $query->getSafeLimit());
    }

    public static function limitDataProvider(): Generator
    {
        yield 'default' => [50, 50];
        yield 'max' => [LogViewerQuery::MAX_LIMIT, LogViewerQuery::MAX_LIMIT];
        yield 'above max' => [1_000_000, LogViewerQuery::MAX_LIMIT];
        yield 'php int max' => [PHP_INT_MAX, LogViewerQuery::MAX_LIMIT];
        yield 'zero' => [0, 1];
        yield 'negative' => [-10, 1];
    }
}
