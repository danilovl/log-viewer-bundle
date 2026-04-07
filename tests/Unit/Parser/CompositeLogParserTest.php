<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Tests\Mock\Parser\{
    CustomNoGoParser,
    CustomGoParser
};
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    DoctrineParser,
    MonologLineParser,
    NginxErrorParser
};
use PHPUnit\Framework\TestCase;

final class CompositeLogParserTest extends TestCase
{
    private CompositeLogParser $compositeParser;

    protected function setUp(): void
    {
        $parsers = [
            new MonologLineParser,
            new DoctrineParser,
            new NginxErrorParser
        ];

        $this->compositeParser = new CompositeLogParser($parsers);
    }

    public function testIdentify(): void
    {
        $line = '2026/04/01 15:24:40 [error] 29622#29622: *19083 upstream timed out';
        $identify = $this->compositeParser->identify($line);

        $this->assertSame('nginx_error', $identify);
    }

    public function testParseMonolog(): void
    {
        $line = '[2026-03-29T09:44:14.945778+00:00] app.INFO: test message [] []';
        $logEntry = $this->compositeParser->parse($line, 'test.log', 'monolog');

        $this->assertSame('2026-03-29T09:44:14.945778+00:00', $logEntry->timestamp);
        $this->assertSame('INFO', $logEntry->level);
        $this->assertSame('app', $logEntry->channel);
    }

    public function testParseDoctrine(): void
    {
        $line = '[2026-03-29T09:44:14.945778+00:00] doctrine.DEBUG: SELECT * FROM users {"sql": "SELECT * FROM users", "params": []}';
        $logEntry = $this->compositeParser->parse($line, 'test.log', 'doctrine');

        $this->assertSame('2026-03-29T09:44:14.945778+00:00', $logEntry->timestamp);
        $this->assertSame('DEBUG', $logEntry->level);
        $this->assertSame('doctrine', $logEntry->channel);
    }

    public function testParseUnsupported(): void
    {
        $line = 'some random line';
        $logEntry = $this->compositeParser->parse($line, 'test.log', 'unknown');

        $this->assertSame('', $logEntry->timestamp);
        $this->assertSame('INFO', $logEntry->level);
        $this->assertSame('some random line', $logEntry->message);
    }

    public function testParseNullType(): void
    {
        $line = 'some random line';
        $logEntry = $this->compositeParser->parse($line, 'test.log', null);

        $this->assertSame('', $logEntry->timestamp);
        $this->assertSame('INFO', $logEntry->level);
        $this->assertSame('some random line', $logEntry->message);
    }

    public function testGetPattern(): void
    {
        $pattern = $this->compositeParser->getPattern('monolog');

        $this->assertNotNull($pattern);
        $this->assertStringContainsString('timestamp', $pattern);

        $this->assertNull($this->compositeParser->getPattern(null));
    }

    public function testGetPatternGo(): void
    {
        $patternGo = $this->compositeParser->getPatternGo('doctrine');

        $this->assertNotNull($patternGo);
        $this->assertStringContainsString('context', $patternGo);

        $patternGoMonolog = $this->compositeParser->getPatternGo('monolog');
        $this->assertNotNull($patternGoMonolog);

        $this->assertNull($this->compositeParser->getPatternGo(null));
    }

    public function testGetParserName(): void
    {
        $parserNameMonolog = $this->compositeParser->getParserName('monolog');
        $this->assertSame('monolog', $parserNameMonolog);

        $parserNameDoctrine = $this->compositeParser->getParserName('doctrine');
        $this->assertSame('doctrine', $parserNameDoctrine);

        $parserNameUnknown = $this->compositeParser->getParserName('unknown');
        $this->assertNull($parserNameUnknown);

        $this->assertNull($this->compositeParser->getParserName(null));
    }

    public function testIsGoParserEnabled(): void
    {
        $customNoGo = new CustomNoGoParser;
        $customGo = new CustomGoParser;

        $this->assertFalse($this->compositeParser->isGoParserEnabled($customNoGo));
        $this->assertTrue($this->compositeParser->isGoParserEnabled($customGo));

        $monolog = new MonologLineParser;
        $this->assertTrue($this->compositeParser->isGoParserEnabled($monolog));
    }
}
