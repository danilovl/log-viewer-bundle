<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Go;

use Danilovl\LogViewerBundle\Service\RegexTemplateProvider;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RegexTemplateGoTest extends TestCase
{
    #[DataProvider('provideRegexMatchGoCases')]
    public function testRegexMatchGo(string $key, string $regex, string $subject, bool $shouldMatch): void
    {
        $escapedRegex = str_replace('/', '\/', $regex);
        $fullRegex = "/{$escapedRegex}/";
        $match = preg_match($fullRegex, $subject);
        $result = $match === 1;

        $this->assertSame($shouldMatch, $result, "Go Key '{$key}' failed for subject '{$subject}'. Regex: {$fullRegex}");
    }

    public static function provideRegexMatchGoCases(): Generator
    {
        yield ['exception', self::getRegex('exception'), 'panic: runtime error: index out of range', true];
        yield ['stack_trace', self::getRegex('stack_trace'), 'goroutine 5 [chan receive]:', true];
        yield ['sql_error', self::getRegex('sql_error'), 'sql: statement is closed', true];
        yield ['memory_limit', self::getRegex('memory_limit'), 'runtime: out of memory', true];
        yield ['url_http_https', self::getRegex('url_http_https'), 'https://example.com', true];
        yield ['url_http_https', self::getRegex('url_http_https'), 'http://localhost:8080', true];
        yield ['url_http_https', self::getRegex('url_http_https'), 'http://app.local:81/bundles/logviewer/build/log_viewer.8260d6ab.css', true];
    }

    private static function getRegex(string $key): string
    {
        foreach (RegexTemplateProvider::SPECIFIC_TEMPLATES as $template) {
            if ($template['key'] === $key) {
                return $template['go'];
            }
        }

        throw new Exception("Go regex for key '{$key}' not found.");
    }
}
