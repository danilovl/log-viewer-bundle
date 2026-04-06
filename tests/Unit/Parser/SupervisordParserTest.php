<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\SupervisordParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupervisordParserTest extends TestCase
{
    private SupervisordParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SupervisordParser;
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
        $this->assertSame('supervisord', $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($filename, $logEntry->file);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('supervisord');
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
        $parserName = $this->parser->getGoParserName('supervisord');

        $this->assertSame('supervisord', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '2026-03-31 20:45:01,123 INFO success: process entered RUNNING state',
            'supervisord.log',
            '2026-03-31 20:45:01,123',
            'INFO',
            'success: process entered RUNNING state'
        ];

        yield [
            '2026-04-01 10:00:05,654 CRIT unexpected exit',
            'supervisord.log',
            '2026-04-01 10:00:05,654',
            'ERROR',
            'unexpected exit'
        ];

        yield [
            'invalid line',
            'supervisord.log',
            '',
            'INFO',
            'invalid line'
        ];
    }
}
