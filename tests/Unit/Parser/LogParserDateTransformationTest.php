<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Parser;

use Danilovl\LogViewerBundle\Parser\{
    AccessLogParser,
    ApacheAccessParser,
    DoctrineParser,
    JsonLogParser,
    ModernSyslogParser,
    MonologLineParser,
    MysqlParser,
    NginxAccessParser,
    NginxErrorParser,
    PhpErrorParser,
    SupervisordParser,
    SyslogParser
};
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogParserDateTransformationTest extends TestCase
{
    #[DataProvider('provideDateFormatAndTransformationCases')]
    public function testDateFormatAndTransformation(
        LogInterfaceParser $parser,
        string $logFile,
        string $expectedFirstLineTimestamp,
        string $expectedNormalized
    ): void {
        $filePath = __DIR__ . '/../../Mock/Log/' . $logFile;
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertNotFalse($content);
        $lines = explode("\n", $content);
        $firstLine = $lines[0];

        $logEntry = $parser->parse($firstLine, $logFile);

        $this->assertSame($expectedFirstLineTimestamp, $logEntry->timestamp, "Failed timestamp for $logFile");
        $this->assertSame($expectedNormalized, $logEntry->normalizedTimestamp, "Failed normalized timestamp for $logFile");

        $this->assertNotEmpty($parser->getDateFormat());
    }

    public static function provideDateFormatAndTransformationCases(): Generator
    {
        yield 'monolog' => [
            new MonologLineParser,
            'monolog.log',
            '2026-03-29T09:44:14.945778+00:00',
            '2026-03-29 09:44:14'
        ];

        yield 'access' => [
            new AccessLogParser,
            'access.log',
            '01/Apr/2026:00:01:20 +0200',
            '2026-04-01 00:01:20'
        ];

        yield 'apache_access' => [
            new ApacheAccessParser,
            'apache_access.log',
            '29/Mar/2026:11:00:00 +0000',
            '2026-03-29 11:00:00'
        ];

        yield 'doctrine' => [
            new DoctrineParser,
            'doctrine.log',
            '2026-03-29T09:44:14.945778+00:00',
            '2026-03-29 09:44:14'
        ];

        yield 'json' => [
            new JsonLogParser,
            'json.log',
            '2026-03-29T10:00:00+00:00',
            '2026-03-29 10:00:00'
        ];

        yield 'auth (modern syslog)' => [
            new ModernSyslogParser,
            'auth.log',
            '2026-03-29T00:05:43.791811+01:00',
            '2026-03-29 00:05:43'
        ];

        yield 'mysql' => [
            new MysqlParser,
            'mysql.log',
            '2020-08-06T14:25:02.835618Z',
            '2020-08-06 14:25:02'
        ];

        yield 'nginx_access' => [
            new NginxAccessParser,
            'nginx_access.log',
            '29/Mar/2026:10:00:00 +0000',
            '2026-03-29 10:00:00'
        ];

        yield 'nginx_error' => [
            new NginxErrorParser,
            'nginx_error.log',
            '2026/04/01 15:24:40',
            '2026-04-01 15:24:40'
        ];

        yield 'php_error' => [
            new PhpErrorParser,
            'php_error.log',
            '2026-03-29T09:44:14.945778+00:00',
            '2026-03-29 09:44:14'
        ];

        yield 'supervisord' => [
            new SupervisordParser,
            'supervisord.log',
            '2026-03-27 21:04:56,966',
            '2026-03-27 21:04:56'
        ];

        yield 'syslog' => [
            new SyslogParser,
            'syslog.log',
            'Oct 11 22:14:15',
            date('Y') . '-10-11 22:14:15'
        ];
    }
}
