<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\NginxAccessParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NginxAccessParserTest extends TestCase
{
    private NginxAccessParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NginxAccessParser;
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
        $supportsNginx = $this->parser->supports('nginx');
        $supportsMonolog = $this->parser->supports('monolog');

        $this->assertTrue($supportsNginx);
        $this->assertFalse($supportsMonolog);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('nginx');

        $this->assertSame('nginx_access', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield ['127.0.0.1 - - [29/Mar/2026:10:00:00 +0000] "GET /index.php HTTP/1.1" 200 1234 "-" "Mozilla/5.0"', 'INFO', 'GET /index.php HTTP/1.1'];
        yield ['192.168.0.1 - - [29/Mar/2026:10:01:00 +0000] "POST /api/test HTTP/1.1" 404 56 "-" "curl/7.68.0"', 'WARNING', 'POST /api/test HTTP/1.1'];
        yield ['10.0.0.1 - - [29/Mar/2026:10:02:00 +0000] "GET /admin HTTP/1.1" 500 0 "-" "-"', 'ERROR', 'GET /admin HTTP/1.1'];
        yield ['invalid line', 'INFO', 'invalid line'];
    }
}
