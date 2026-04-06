<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\DoctrineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Generator;

final class DoctrineParserTest extends TestCase
{
    private DoctrineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DoctrineParser;
    }

    /**
     * @param string[]|null $expectedParams
     */
    #[DataProvider('provideParseCases')]
    public function testParse(
        string $line,
        string $filename,
        string $expectedTimestamp,
        string $expectedLevel,
        string $expectedChannel,
        string $expectedMessage,
        ?string $expectedSql,
        ?array $expectedParams
    ): void {
        $logEntry = $this->parser->parse($line, $filename);

        $this->assertSame($expectedTimestamp, $logEntry->timestamp);
        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame($expectedChannel, $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($filename, $logEntry->file);
        $this->assertSame($expectedSql, $logEntry->sql);
        $this->assertSame($expectedParams, $logEntry->parameters);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('doctrine');
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
        $patternGo = $this->parser->getGoPattern('doctrine');

        $this->assertNotEmpty($patternGo);
    }

    public function testGetParserName(): void
    {
        $parserName = $this->parser->getGoParserName('doctrine');

        $this->assertSame('doctrine', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '[2026-03-29T09:44:14.945778+00:00] doctrine.DEBUG: SELECT * FROM users {"sql": "SELECT * FROM users", "params": []}',
            'doctrine.log',
            '2026-03-29T09:44:14.945778+00:00',
            'DEBUG',
            'doctrine',
            'SELECT * FROM users',
            'SELECT * FROM users',
            []
        ];

        yield [
            '[2026-03-29T09:44:15.945778+00:00] doctrine.DEBUG: UPDATE users SET name = ? WHERE id = ? {"sql": "UPDATE users SET name = ? WHERE id = ?", "params": ["John", 1]}',
            'doctrine.log',
            '2026-03-29T09:44:15.945778+00:00',
            'DEBUG',
            'doctrine',
            'UPDATE users SET name = ? WHERE id = ?',
            'UPDATE users SET name = ? WHERE id = ?',
            ['John', 1]
        ];

        yield [
            'invalid line',
            'doctrine.log',
            '',
            'INFO',
            'doctrine',
            'invalid line',
            null,
            null
        ];
    }
}
