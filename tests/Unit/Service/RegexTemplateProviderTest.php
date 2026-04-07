<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Service;

use Danilovl\LogViewerBundle\Service\{
    RegexTemplateProvider,
    ConfigurationProvider
};
use Generator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Exception;

class RegexTemplateProviderTest extends TestCase
{
    /**
     * @param string[] $expectedKeys
     */
    #[DataProvider('provideGetTemplatesCases')]
    public function testGetTemplates(bool $goEnabled, array $expectedKeys): void
    {
        $configurationProvider = $this->createConfigurationProvider($goEnabled);
        $regexTemplateProvider = new RegexTemplateProvider($configurationProvider);

        $templates = $regexTemplateProvider->getTemplates();
        $keys = array_column($templates, 'key');

        foreach ($expectedKeys as $key) {
            $this->assertContains($key, $keys);
        }
    }

    #[DataProvider('provideRegexMatchCases')]
    public function testRegexMatch(string $key, string $regex, string $subject, bool $shouldMatch): void
    {
        $escapedRegex = str_replace('/', '\/', $regex);
        $fullRegex = "/{$escapedRegex}/";
        $match = @preg_match($fullRegex, $subject);
        $result = $match === 1;

        $this->assertSame($shouldMatch, $result, "Key '{$key}' failed for subject '{$subject}'. Regex: {$fullRegex}");
    }

    public static function provideGetTemplatesCases(): Generator
    {
        yield [false, ['ipv4_address', 'email_address', 'exception', 'sql_error', 'stack_trace']];
        yield [true, ['ipv4_address', 'email_address', 'exception', 'stack_trace', 'memory_limit']];
    }

    public static function provideRegexMatchCases(): Generator
    {
        // PHP matches
        yield ['symfony_deprecation_php', self::getSpecificRegex('symfony_deprecation', 'php'), 'User Deprecated: Some feature is deprecated', true];
        yield ['symfony_security_php', self::getSpecificRegex('symfony_security', 'php'), 'Authentication request failed: Bad credentials', true];
        yield ['symfony_messenger_php', self::getSpecificRegex('symfony_messenger', 'php'), 'Error thrown while handling message App\Message\MyMessage. Sending for retry', true];
        yield ['doctrine_query_time_php', self::getSpecificRegex('doctrine_query_time', 'php'), 'total time: 25.5 ms', true];
        yield ['php_syntax_error_php', self::getSpecificRegex('php_syntax_error', 'php'), 'PHP Parse error: syntax error, unexpected token ";"', true];
        yield ['php_warning_php', self::getSpecificRegex('php_warning', 'php'), 'PHP Warning: Division by zero in file.php:10', true];
        yield ['php_notice_php', self::getSpecificRegex('php_notice', 'php'), 'PHP Notice: Undefined variable $x in file.php:5', true];
        yield ['twig_error_php', self::getSpecificRegex('twig_error', 'php'), 'Twig\Error\LoaderError: Template "base.html.twig" not found.', true];
        yield ['exception_php', self::getSpecificRegex('exception', 'php'), 'Exception: Some error in /path/to/file.php:123', true];
        yield ['sql_error_php', self::getSpecificRegex('sql_error', 'php'), 'SQLSTATE[42S02]: Base table or view not found', true];
        yield ['stack_trace_php', self::getSpecificRegex('stack_trace', 'php'), '#0 /path/to/file.php(10): SomeClass->method()', true];
        yield ['memory_limit_php', self::getSpecificRegex('memory_limit', 'php'), 'Allowed memory size of 123456 bytes exhausted', true];
        yield ['fatal_error_php', self::getSpecificRegex('fatal_error', 'php'), 'Fatal error: Uncaught Error in /path/to/file.php on line 45', true];
        yield ['framework_route_php', self::getSpecificRegex('framework_route', 'php'), 'Matched route "app_homepage"', true];
        yield ['database_query_php', self::getSpecificRegex('database_query', 'php'), 'SELECT * FROM users WHERE id = 1', true];
        yield ['sensitive_data_cc_php', self::getSpecificRegex('sensitive_data_cc', 'php'), '4111111111111111', true];
        yield ['http_status_error_php', self::getSpecificRegex('http_status_error', 'php'), '404', true];
        yield ['db_connection_error_php', self::getSpecificRegex('db_connection_error', 'php'), 'SQLSTATE[HY000] [2002] Connection refused', true];

        // Go matches
        yield ['exception_go', self::getSpecificRegex('exception', 'go'), 'panic: something went wrong', true];
        yield ['sql_error_go', self::getSpecificRegex('sql_error', 'go'), 'sql: no rows in result set', true];
        yield ['stack_trace_go', self::getSpecificRegex('stack_trace', 'go'), 'goroutine 1 [running]:', true];
        yield ['memory_limit_go', self::getSpecificRegex('memory_limit', 'go'), 'runtime: out of memory', true];
        yield ['fatal_error_go', self::getSpecificRegex('fatal_error', 'go'), 'fatal error: concurrent map writes', true];
        yield ['framework_route_go', self::getSpecificRegex('framework_route', 'go'), 'http: panic serving 127.0.0.1:1234: some error', true];
        yield ['database_query_go', self::getSpecificRegex('database_query', 'go'), 'sql: SELECT * FROM users', true];
        yield ['sensitive_data_cc_go', self::getSpecificRegex('sensitive_data_cc', 'go'), '4111111111111111', true];
        yield ['http_status_error_go', self::getSpecificRegex('http_status_error', 'go'), 'rpc error: code = Unimplemented desc = unknown service', true];
        yield ['db_connection_error_go', self::getSpecificRegex('db_connection_error', 'go'), 'connection refused', true];

        // Universal matches (using PHP side for extraction)
        yield ['ipv4_address', self::getSpecificRegex('ipv4_address', 'php'), '127.0.0.1', true];
        yield ['ipv4_address', self::getSpecificRegex('ipv4_address', 'php'), '255.255.255.255', true];
        yield ['ipv4_address', self::getSpecificRegex('ipv4_address', 'php'), '256.0.0.1', false];
        yield ['ipv4_address', self::getSpecificRegex('ipv4_address', 'php'), 'not an ip', false];

        yield ['uuid_guid', self::getSpecificRegex('uuid_guid', 'php'), '550e8400-e29b-41d4-a716-446655440000', true];
        yield ['uuid_guid', self::getSpecificRegex('uuid_guid', 'php'), '550E8400-E29B-41D4-A716-446655440000', true];
        yield ['uuid_guid', self::getSpecificRegex('uuid_guid', 'php'), 'invalid-uuid', false];

        yield ['iso_8601_date', self::getSpecificRegex('iso_8601_date', 'php'), '2023-10-27 10:00:00', true];
        yield ['iso_8601_date', self::getSpecificRegex('iso_8601_date', 'php'), '2023-10-27T10:00:00Z', true];
        yield ['iso_8601_date', self::getSpecificRegex('iso_8601_date', 'php'), '2023-10-27T10:00:00+02:00', true];
        yield ['iso_8601_date', self::getSpecificRegex('iso_8601_date', 'php'), '2023-10-27', false];

        yield ['email_address', self::getSpecificRegex('email_address', 'php'), 'test@example.com', true];
        yield ['email_address', self::getSpecificRegex('email_address', 'php'), 'invalid-email', false];

        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), '2001:0db8:85a3:0000:0000:8a2e:0370:7334', true];
        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), '2001:db8:85a3:0:0:8a2e:370:7334', true];
        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), '2001:db8:85a3::8a2e:370:7334', true];
        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), '::1', true];
        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), 'not:ipv6', false];
        yield ['ipv6_address', self::getSpecificRegex('ipv6_address', 'php'), 'App\Domain\Wallet\Controller\WalletController::asd9()', false];

        yield ['mac_address', self::getSpecificRegex('mac_address', 'php'), '00:1A:2B:3C:4D:5E', true];
        yield ['mac_address', self::getSpecificRegex('mac_address', 'php'), '00-1A-2B-3C-4D-5E', true];
        yield ['mac_address', self::getSpecificRegex('mac_address', 'php'), '001A2B3C4D5E', false];

        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'http://www.google.com/search?q=test', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'http://localhost:8080', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://sub-domain.example.co.uk/path?arg=val#fragment', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://192.168.1.1:8443', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'http://app.local:81/bundles/logviewer/build/log_viewer.8260d6ab.css', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'http://example.com/foo_bar', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com/foo.bar', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'http://example.com/foo(bar)', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com/foo~bar', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com/foo#bar', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com/some/path?query=1&arg=2', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'https://example.com/some/path?query=1&arg=2#fragment', true];
        yield ['url_http_https', self::getSpecificRegex('url_http_https', 'php'), 'ftp://example.com', false];

        yield ['hex_color', self::getSpecificRegex('hex_color', 'php'), '#FFFFFF', true];
        yield ['hex_color', self::getSpecificRegex('hex_color', 'php'), '#abc', true];
        yield ['hex_color', self::getSpecificRegex('hex_color', 'php'), 'FFFFFF', false];

        yield ['phone_number', self::getSpecificRegex('phone_number', 'php'), '+7 999 123-45-67', true];
        yield ['phone_number', self::getSpecificRegex('phone_number', 'php'), '001 123 456 7890', true];
        yield ['phone_number', self::getSpecificRegex('phone_number', 'php'), '123-456-7890', true];
        yield ['phone_number', self::getSpecificRegex('phone_number', 'php'), 'not a phone', false];

        yield ['credit_card_mask', self::getSpecificRegex('credit_card_mask', 'php'), '4111-XXXX-XXXX-1234', true];
        yield ['credit_card_mask', self::getSpecificRegex('credit_card_mask', 'php'), 'XXXXXXXXXXXX1234', true];
        yield ['credit_card_mask', self::getSpecificRegex('credit_card_mask', 'php'), '4111111111111111', false];

        yield ['ip_with_port', self::getSpecificRegex('ip_with_port', 'php'), '192.168.1.1:8080', true];
        yield ['timestamp_brackets', self::getSpecificRegex('timestamp_brackets', 'php'), '[2023-10-27 10:00:00]', true];
        yield ['json_block', self::getSpecificRegex('json_block', 'php'), '{"key": "value"}', true];
        yield ['json_block', self::getSpecificRegex('json_block', 'php'), '[1, 2, 3]', true];

        yield ['ipv6_with_port', self::getSpecificRegex('ipv6_with_port', 'php'), '[2001:db8::1]:8080', true];
        yield ['unix_timestamp', self::getSpecificRegex('unix_timestamp', 'php'), '1672531200', true];
        yield ['unix_timestamp', self::getSpecificRegex('unix_timestamp', 'php'), '1672531200.123', true];
        yield ['log_level', self::getSpecificRegex('log_level', 'php'), 'ERROR', true];
        yield ['log_level', self::getSpecificRegex('log_level', 'php'), 'CRITICAL', true];
        yield ['quoted_string', self::getSpecificRegex('quoted_string', 'php'), '"hello \"world\""', true];
        yield ['quoted_string', self::getSpecificRegex('quoted_string', 'php'), "'it\'s fine'", true];

        // New PHP matches
        yield ['file_path_php', self::getSpecificRegex('file_path', 'php'), '/var/log/nginx/error.log', true];
        yield ['file_path_php', self::getSpecificRegex('file_path', 'php'), 'C:\\Windows\\System32\\drivers\\etc\\hosts', true];
        yield ['http_method_php', self::getSpecificRegex('http_method', 'php'), 'POST', true];
        yield ['memory_usage_php', self::getSpecificRegex('memory_usage', 'php'), '128 MB', true];
        yield ['runtime_version_php', self::getSpecificRegex('runtime_version', 'php'), 'PHP 8.3.4', true];
        yield ['request_id_php', self::getSpecificRegex('request_id', 'php'), 'req-123-abc', true];
        yield ['request_id_php', self::getSpecificRegex('request_id', 'php'), 'X-Request-ID: abc-123', true];
        yield ['environment_php', self::getSpecificRegex('environment', 'php'), 'env:prod', true];
        yield ['time_duration_php', self::getSpecificRegex('time_duration', 'php'), '150.5ms', true];
        yield ['extension_module_php', self::getSpecificRegex('extension_module', 'php'), 'extension "redis" is missing', true];
        yield ['auth_user_php', self::getSpecificRegex('auth_user', 'php'), 'user_id:42', true];
        yield ['stack_frame_php', self::getSpecificRegex('stack_frame', 'php'), 'at /path/to/file.php:88', true];

        // New Go matches
        yield ['file_path_go', self::getSpecificRegex('file_path', 'go'), '/usr/local/bin/go', true];
        yield ['http_method_go', self::getSpecificRegex('http_method', 'go'), 'GET', true];
        yield ['memory_usage_go', self::getSpecificRegex('memory_usage', 'go'), 'Alloc = 123 TotalAlloc = 456 Sys = 789 NumGC = 1', true];
        yield ['runtime_version_go', self::getSpecificRegex('runtime_version', 'go'), 'go version go1.22.1', true];
        yield ['request_id_go', self::getSpecificRegex('request_id', 'go'), 'request_id=xyz-789', true];
        yield ['environment_go', self::getSpecificRegex('environment', 'go'), 'environment=staging', true];
        yield ['time_duration_go', self::getSpecificRegex('time_duration', 'go'), '2h30m', true];
        yield ['extension_module_go', self::getSpecificRegex('extension_module', 'go'), 'module github.com/foo/bar not found', true];
        yield ['auth_user_go', self::getSpecificRegex('auth_user', 'go'), 'uid:1001', true];
        yield ['stack_frame_go', self::getSpecificRegex('stack_frame', 'go'), 'main.go:42', true];

        // Even more matches from Mock logs
        yield ['http_referrer_php', self::getSpecificRegex('http_referrer', 'php'), 'referrer: "http://app.local:81/test/log-viewer/logs/99fe3432e46e"', true];
        yield ['user_agent_php', self::getSpecificRegex('user_agent', 'php'), '"Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0"', true];
        yield ['process_id_php', self::getSpecificRegex('process_id', 'php'), 'supervisord started with pid 733', true];
        yield ['upstream_address_php', self::getSpecificRegex('upstream_address', 'php'), 'upstream: "fastcgi://unix:/var/run/php/php8.5-fpm.sock"', true];
        yield ['supervisor_status_php', self::getSpecificRegex('supervisor_status', 'php'), 'success: symfony-scheduler entered RUNNING state', true];
        yield ['cron_task_php', self::getSpecificRegex('cron_task', 'php'), 'CRON[96115]: pam_unix(cron:session): session opened', true];
        yield ['kernel_message_php', self::getSpecificRegex('kernel_message', 'php'), 'kernel: vethaecd40d: renamed from eth0', true];
        yield ['log_channel_php', self::getSpecificRegex('log_channel', 'php'), 'app.ERROR: error message', true];
        yield ['http_version_php', self::getSpecificRegex('http_version', 'php'), 'GET / HTTP/1.1', true];
        yield ['db_transaction_php', self::getSpecificRegex('db_transaction', 'php'), 'START TRANSACTION', true];
    }

    private static function getSpecificRegex(string $key, string $type): string
    {
        foreach (RegexTemplateProvider::SPECIFIC_TEMPLATES as $template) {
            if ($template['key'] === $key) {
                return $template[$type];
            }
        }

        throw new Exception("Specific regex for key '{$key}' and type '{$type}' not found.");
    }

    private function createConfigurationProvider(bool $goEnabled): ConfigurationProvider
    {
        return new ConfigurationProvider(
            sourceDirs: [],
            sourceFiles: [],
            sourceIgnore: [],
            sourceMaxFileSize: null,
            sourceAllowDelete: false,
            sourceAllowDownload: false,
            parserDefault: null,
            parserOverrides: [],
            parserGoEnabled: $goEnabled,
            parserGoBinaryPath: '',
            cacheParserDetectEnabled: false,
            cacheStatisticEnabled: false,
            cacheStatisticInterval: 0,
            dashboardPageStatisticEnabled: false,
            dashboardPageAutoRefreshEnabled: false,
            dashboardPageAutoRefreshInterval: 0,
            dashboardPageAutoRefreshShowCountdown: false,
            liveLogPageEnabled: false,
            liveLogPageInterval: 0,
            logPageStatisticEnabled: false,
            logPageAutoRefreshEnabled: false,
            logPageAutoRefreshInterval: 0,
            logPageAutoRefreshShowCountdown: false,
            logPageLimit: 0,
            aiButtonLevels: [],
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: false,
            notifierRules: []
        );
    }
}
