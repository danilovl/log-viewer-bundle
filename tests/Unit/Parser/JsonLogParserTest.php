<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Interfaces\LogParserGoPatternInterface;
use Danilovl\LogViewerBundle\Parser\{

    JsonLogParser
};
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonLogParserTest extends TestCase
{
    private JsonLogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonLogParser;
    }

    #[DataProvider('provideParseCases')]
    public function testParse(
        string $line,
        string $filename,
        string $expectedTimestamp,
        string $expectedLevel,
        string $expectedChannel,
        string $expectedMessage
    ): void {
        $logEntry = $this->parser->parse($line, $filename);

        $this->assertSame($expectedTimestamp, $logEntry->timestamp);
        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame($expectedChannel, $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($filename, $logEntry->file);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('json');
        $this->assertTrue($isSupported);

        $isNotSupported = $this->parser->supports('other');
        $this->assertFalse($isNotSupported);
    }

    public function testGetPattern(): void
    {
        $pattern = $this->parser->getPattern();

        $this->assertNotEmpty($pattern);
    }

    public function testGetPatternGo(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertNotInstanceOf(LogParserGoPatternInterface::class, $this->parser);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('json');

        $this->assertSame('json', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '{"timestamp": "2026-03-31T20:45:01+00:00", "level": "INFO", "channel": "app", "message": "json message"}',
            'json.log',
            '2026-03-31T20:45:01+00:00',
            'INFO',
            'app',
            'json message'
        ];

        yield [
            '{"timestamp": "2026-04-01T10:00:05+00:00", "level": "ERROR", "message": "error"}',
            'json.log',
            '2026-04-01T10:00:05+00:00',
            'ERROR',
            'app',
            'error'
        ];

        yield [
            'invalid json',
            'json.log',
            '',
            'INFO',
            'json',
            'invalid json'
        ];
    }
}
