<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\MysqlParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MysqlParserTest extends TestCase
{
    private MysqlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MysqlParser;
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
        $isSupported = $this->parser->supports('mysql');
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
        $parserName = $this->parser->getGoParserName('mysql');

        $this->assertSame('mysql', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '2026-03-31T20:45:01.123456Z 1 [Note] [MY-010119] [Server] Aborting',
            'mysql.log',
            '2026-03-31T20:45:01.123456Z',
            'INFO',
            'Server',
            'Aborting'
        ];

        yield [
            '2026-04-01T10:00:05.654321Z 123 [ERROR] [MY-012345] [InnoDB] Some error',
            'mysql.log',
            '2026-04-01T10:00:05.654321Z',
            'ERROR',
            'InnoDB',
            'Some error'
        ];

        yield [
            'invalid line',
            'mysql.log',
            '',
            'INFO',
            'mysql',
            'invalid line'
        ];
    }
}
