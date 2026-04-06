<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\MonologLineParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MonologLineParserTest extends TestCase
{
    private MonologLineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MonologLineParser;
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
        $isSupported = $this->parser->supports('monolog');
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
        $parserName = $this->parser->getGoParserName('monolog');

        $this->assertSame('monolog', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '[2026-03-29T09:44:14.945778+00:00] app.INFO: test message [] []',
            'test.log',
            '2026-03-29T09:44:14.945778+00:00',
            'INFO',
            'app',
            'test message'
        ];

        yield [
            '[2026-03-29T09:44:15.945778+00:00] app.ERROR: error message {"key": "value"} {"extra": "data"}',
            'test.log',
            '2026-03-29T09:44:15.945778+00:00',
            'ERROR',
            'app',
            'error message'
        ];

        yield [
            '[2026-03-29T09:44:16.945778+00:00] request.CRITICAL: critical message {"user_id": 1} []',
            'test.log',
            '2026-03-29T09:44:16.945778+00:00',
            'CRITICAL',
            'request',
            'critical message'
        ];

        yield [
            'invalid line',
            'test.log',
            '',
            'INFO',
            'app',
            'invalid line'
        ];
    }
}
