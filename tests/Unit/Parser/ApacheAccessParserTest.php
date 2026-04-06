<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\{
    ApacheAccessParser
};
use Danilovl\LogViewerBundle\Interfaces\LogParserGoPatternInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApacheAccessParserTest extends TestCase
{
    private ApacheAccessParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ApacheAccessParser;
    }

    #[DataProvider('provideParseCases')]
    public function testParse(string $line, string $expectedLevel, string $expectedMessage): void
    {
        $logEntry = $this->parser->parse($line, 'test.log');

        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame($expectedMessage, $logEntry->message);
    }

    public function testSupports(): void
    {
        $supportsApache = $this->parser->supports('apache');
        $supportsNginx = $this->parser->supports('nginx');

        $this->assertTrue($supportsApache);
        $this->assertFalse($supportsNginx);
    }

    public function testParserInterfaces(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertNotInstanceOf(LogParserGoPatternInterface::class, $this->parser);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('apache');

        $this->assertSame('apache_access', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield ['127.0.0.1 - - [29/Mar/2026:11:00:00 +0000] "GET / HTTP/1.1" 200 225', 'INFO', 'GET / HTTP/1.1'];
        yield ['192.168.0.1 - - [29/Mar/2026:11:01:00 +0000] "GET /favicon.ico HTTP/1.1" 404 0', 'WARNING', 'GET /favicon.ico HTTP/1.1'];
        yield ['10.0.0.1 - - [29/Mar/2026:11:02:00 +0000] "POST /login HTTP/1.1" 503 120', 'ERROR', 'POST /login HTTP/1.1'];
        yield ['invalid line', 'INFO', 'invalid line'];
    }
}
