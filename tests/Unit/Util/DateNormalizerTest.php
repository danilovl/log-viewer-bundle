<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Util;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateNormalizerTest extends TestCase
{
    #[DataProvider('provideNormalizeCases')]
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, DateNormalizer::normalize($input));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function provideNormalizeCases(): array
    {
        return [
            ['2026-03-29T09:44:14.945778+00:00', '2026-03-29 09:44:14'],
            ['01/Apr/2026:00:01:20 +0200', '2026-04-01 00:01:20'],
            ['2026/04/01 15:24:40', '2026-04-01 15:24:40'],
            ['29-Mar-2026 09:44:14 UTC', '2026-03-29 09:44:14'],
            ['2026-03-27 21:04:56,966', '2026-03-27 21:04:56'],
            ['Oct 11 22:14:15', date('Y') . '-10-11 22:14:15'],
            ['', ''],
            ['invalid', 'invalid'],
        ];
    }
}
