<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\PhpErrorParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpErrorParserTest extends TestCase
{
    private PhpErrorParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PhpErrorParser;
    }

    /**
     * @param array<string, string> $expectedContext
     */
    #[DataProvider('provideParseCases')]
    public function testParse(
        string $line,
        string $filename,
        string $expectedTimestamp,
        string $expectedLevel,
        string $expectedChannel,
        string $expectedMessage,
        string $expectedFile,
        array $expectedContext
    ): void {
        $logEntry = $this->parser->parse($line, $filename);

        $this->assertSame($expectedTimestamp, $logEntry->timestamp);
        $this->assertSame($expectedLevel, $logEntry->level);
        $this->assertSame($expectedChannel, $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($expectedFile, $logEntry->file);
        $this->assertSame($expectedContext, $logEntry->context);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('php');
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
        $parserName = $this->parser->getGoParserName('php');

        $this->assertSame('php_error', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '[2026-03-29T09:44:14.945778+00:00] PHP Fatal error:  Uncaught Error: Call to undefined function test() in /var/www/index.php on line 10',
            'php.log',
            '2026-03-29T09:44:14.945778+00:00',
            'FATAL ERROR',
            'php',
            'Uncaught Error: Call to undefined function test()',
            '/var/www/index.php',
            ['line' => '10'],
        ];

        yield [
            '[2026-03-29T09:44:15.945778+00:00] PHP Warning:  Undefined variable $x in /var/www/index.php on line 20',
            'php.log',
            '2026-03-29T09:44:15.945778+00:00',
            'WARNING',
            'php',
            'Undefined variable $x',
            '/var/www/index.php',
            ['line' => '20'],
        ];

        yield [
            'invalid line',
            'php.log',
            '',
            'ERROR',
            'php',
            'invalid line',
            'php.log',
            [],
        ];
    }
}
