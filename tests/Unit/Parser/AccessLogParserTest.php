<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\AccessLogParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Generator;

final class AccessLogParserTest extends TestCase
{
    private AccessLogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AccessLogParser;
    }

    #[DataProvider('provideParseCases')]
    public function testParse(
        string $line,
        string $filename,
        string $expectedTimestamp,
        string $expectedLevel,
        string $expectedMessage
    ): void {
        $logEntry = $this->parser->parse($line, $filename);

        $this->assertSame($expectedTimestamp, $logEntry->timestamp);
        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame('access', $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($filename, $logEntry->file);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('access');
        $this->assertTrue($isSupported);

        $isNotSupported = $this->parser->supports('other');
        $this->assertFalse($isNotSupported);
    }

    public function testGetPattern(): void
    {
        $pattern = $this->parser->getPattern();

        $this->assertNotEmpty($pattern);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('access');

        $this->assertSame('access', $parserName);
    }

    public static function provideParseCases(): Generator
{
    yield [
        '127.0.0.1 - - [31/Mar/2026:20:45:01 +0000] "GET / HTTP/1.1" 200 123 "-" "Mozilla/5.0"',
        'access.log',
        '31/Mar/2026:20:45:01 +0000',
        'INFO',
        'GET / HTTP/1.1'
    ];

    yield [
        '192.168.1.1 - user [01/Apr/2026:10:00:05 +0000] "POST /login HTTP/1.1" 401 45 "http://referer" "Agent"',
        'access.log',
        '01/Apr/2026:10:00:05 +0000',
        'WARNING',
        'POST /login HTTP/1.1'
    ];

    yield [
        '8.8.8.8 - - [01/Apr/2026:11:00:00 +0000] "GET /error HTTP/1.1" 500 0 "-" "-"',
        'access.log',
        '01/Apr/2026:11:00:00 +0000',
        'ERROR',
        'GET /error HTTP/1.1'
    ];

    yield [
        'invalid line',
        'access.log',
        '',
        'INFO',
        'invalid line'
    ];
}
}
