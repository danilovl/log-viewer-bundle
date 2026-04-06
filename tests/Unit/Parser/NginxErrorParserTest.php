<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\NginxErrorParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NginxErrorParserTest extends TestCase
{
    private NginxErrorParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NginxErrorParser;
    }

    #[DataProvider('provideParseCases')]
    public function testParse(string $line, string $expectedLevel, string $expectedMessage, ?string $expectedCid = null): void
    {
        $logEntry = $this->parser->parse($line, 'test.log');

        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($expectedCid, $logEntry->context['cid'] ?? null);
    }

    public function testSupports(): void
    {
        $supportsNginxError = $this->parser->supports('nginx_error');
        $supportsNginx = $this->parser->supports('nginx');

        $this->assertTrue($supportsNginxError);
        $this->assertFalse($supportsNginx);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('nginx_error');

        $this->assertSame('nginx_error', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '2026/04/01 15:24:40 [error] 29622#29622: *19083 upstream timed out',
            'ERROR',
            'upstream timed out',
            '19083'
        ];
        yield [
            '2026/04/01 15:24:40 [warn] 29622#29622: message without cid',
            'WARN',
            'message without cid',
            null
        ];
        yield ['invalid line', 'INFO', 'invalid line', null];
    }
}
