<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\ModernSyslogParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModernSyslogParserTest extends TestCase
{
    private ModernSyslogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ModernSyslogParser;
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

    #[DataProvider('provideSupportsCases')]
    public function testSupports(string $type, bool $expected): void
    {
        $this->assertSame($expected, $this->parser->supports($type));
    }

    public function testGetPattern(): void
    {
        $pattern = $this->parser->getPattern();

        $this->assertNotEmpty($pattern);
    }

    public function testGetParserName(): void
    {
        $this->assertSame('auth', $this->parser->getGoParserName('auth'));
        $this->assertSame('kern', $this->parser->getGoParserName('kern'));
        $this->assertSame('php_fpm', $this->parser->getGoParserName('php-fpm'));
        $this->assertSame('syslog_modern', $this->parser->getGoParserName('syslog-modern'));
    }

    public static function provideSupportsCases(): Generator
    {
        yield ['auth', true];
        yield ['kern', true];
        yield ['php-fpm', true];
        yield ['syslog-modern', true];
        yield ['monolog', false];
        yield ['other', false];
    }

    public static function provideParseCases(): Generator
    {
        yield [
            '2026-03-31T20:45:01.123456+00:00 localhost systemd: Started Session.',
            'auth.log',
            '2026-03-31T20:45:01.123456+00:00',
            'systemd',
            'Started Session.'
        ];

        yield [
            '2026-04-01T10:00:05.654321Z myhost kern: [123.456] eth0: link up',
            'kern.log',
            '2026-04-01T10:00:05.654321Z',
            'kern',
            '[123.456] eth0: link up'
        ];

        yield [
            'invalid line',
            'test.log',
            '',
            'syslog',
            'invalid line'
        ];
    }
}
