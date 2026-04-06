<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\SyslogParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SyslogParserTest extends TestCase
{
    private SyslogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SyslogParser;
    }

    #[DataProvider('provideParseCases')]
    public function testParse(
        string $line,
        string $filename,
        string $expectedTimestamp,
        string $expectedChannel,
        string $expectedMessage
    ): void {
        $logEntry = $this->parser->parse($line, $filename);

        $this->assertSame($expectedTimestamp, $logEntry->timestamp);
        $this->assertSame('INFO', $logEntry->level);
        $this->assertSame($expectedChannel, $logEntry->channel);
        $this->assertSame($expectedMessage, $logEntry->message);
        $this->assertSame($filename, $logEntry->file);
    }

    public function testSupports(): void
    {
        $isSupported = $this->parser->supports('syslog');
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
        $parserName = $this->parser->getGoParserName('syslog');

        $this->assertSame('syslog', $parserName);
    }

    public static function provideParseCases(): Generator
    {
        yield [
            'Mar 31 20:45:01 localhost CRON[1234]: (root) CMD (command)',
            'syslog.log',
            'Mar 31 20:45:01',
            'CRON',
            '(root) CMD (command)'
        ];

        yield [
            'Apr  1 10:00:05 myhost systemd: Started Session 1 of user test.',
            'syslog.log',
            'Apr  1 10:00:05',
            'systemd',
            'Started Session 1 of user test.'
        ];

        yield [
            'invalid line',
            'syslog.log',
            '',
            'syslog',
            'invalid line'
        ];
    }
}
