<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Service;

readonly class RegexTemplateProvider
{
    public const array SPECIFIC_TEMPLATES = [
        [
            'key' => 'symfony_deprecation',
            'label' => 'Symfony Deprecation',
            'php' => '(?:User Deprecated|deprecated): .*',
            'go' => '(?:User Deprecated|deprecated): .*'
        ],
        [
            'key' => 'symfony_security',
            'label' => 'Symfony Security',
            'php' => '(?:Authentication (?:request failed|failure)|auth): .*',
            'go' => '(?:Authentication (?:request failed|failure)|auth): .*'
        ],
        [
            'key' => 'symfony_messenger',
            'label' => 'Symfony Messenger',
            'php' => '(?:Error thrown while handling message .*\\. Sending for retry|messenger: .*)',
            'go' => '(?:Error thrown while handling message .*\\. Sending for retry|messenger: .*)'
        ],
        [
            'key' => 'symfony_wdt',
            'label' => 'Symfony WDT',
            'php' => '/_wdt/[a-f0-9]+',
            'go' => '/_wdt/[a-f0-9]+'
        ],
        [
            'key' => 'php_syntax_error',
            'label' => 'PHP Syntax Error',
            'php' => '(?:PHP Parse error: syntax error, |syntax error: ).*',
            'go' => '(?:PHP Parse error: syntax error, |syntax error: ).*'
        ],
        [
            'key' => 'php_warning',
            'label' => 'PHP Warning',
            'php' => '(?:PHP )?Warning: .*',
            'go' => '(?:PHP )?Warning: .*'
        ],
        [
            'key' => 'php_notice',
            'label' => 'PHP Notice',
            'php' => '(?:PHP )?Notice: .*',
            'go' => '(?:PHP )?Notice: .*'
        ],
        [
            'key' => 'twig_error',
            'label' => 'Twig Template Error',
            'php' => '(?:Twig\\\\Error\\\\LoaderError|twig): .*',
            'go' => '(?:Twig\\\\Error\\\\LoaderError|twig): .*'
        ],
        [
            'key' => 'exception',
            'label' => 'Exception',
            'php' => '(?:(?:Exception|Error): .* in .*:[0-9]+|panic: .*)',
            'go' => '(?:(?:Exception|Error): .* in .*:[0-9]+|panic: .*)'
        ],
        [
            'key' => 'fatal_error',
            'label' => 'Fatal Error',
            'php' => '(?:Fatal error: .* in .* on line [0-9]+|fatal error: .*)',
            'go' => '(?:Fatal error: .* in .* on line [0-9]+|fatal error: .*)'
        ],
        [
            'key' => 'stack_trace',
            'label' => 'Stack Trace',
            'php' => '(?:#[0-9]+ .*\\([0-9]+\\): .*|goroutine [0-9]+ \\[.*\\]:)',
            'go' => '(?:#[0-9]+ .*\\([0-9]+\\): .*|goroutine [0-9]+ \\[.*\\]:)'
        ],
        [
            'key' => 'stack_frame',
            'label' => 'Stack Frame',
            'php' => '(?:at .*:[0-9]+|.*\\.go:[0-9]+)',
            'go' => '(?:at .*:[0-9]+|.*\\.go:[0-9]+)'
        ],
        // SQL Group
        [
            'key' => 'sql_select',
            'label' => 'SQL Select',
            'php' => '\\bSELECT\\s+.*?\\s+FROM\\b',
            'go' => '\\bSELECT\\s+.*?\\s+FROM\\b'
        ],
        [
            'key' => 'sql_insert',
            'label' => 'SQL Insert',
            'php' => '\\bINSERT\\s+INTO\\b',
            'go' => '\\bINSERT\\s+INTO\\b'
        ],
        [
            'key' => 'sql_update',
            'label' => 'SQL Update',
            'php' => '\\bUPDATE\\s+.*?\\s+SET\\b',
            'go' => '\\bUPDATE\\s+.*?\\s+SET\\b'
        ],
        [
            'key' => 'sql_delete',
            'label' => 'SQL Delete',
            'php' => '\\bDELETE\\s+FROM\\b',
            'go' => '\\bDELETE\\s+FROM\\b'
        ],
        [
            'key' => 'sql_subselect',
            'label' => 'SQL Sub-select',
            'php' => '\\(\\s*SELECT\\b',
            'go' => '\\(\\s*SELECT\\b'
        ],
        [
            'key' => 'sql_join',
            'label' => 'SQL Join',
            'php' => '\\b(?:INNER|LEFT|RIGHT|CROSS)?\\s*JOIN\\b',
            'go' => '\\b(?:INNER|LEFT|RIGHT|CROSS)?\\s*JOIN\\b'
        ],
        [
            'key' => 'sql_where',
            'label' => 'SQL Where',
            'php' => '\\bWHERE\\b',
            'go' => '\\bWHERE\\b'
        ],
        [
            'key' => 'sql_order_limit',
            'label' => 'SQL Order / Limit',
            'php' => '\\b(?:ORDER\\s+BY|LIMIT|OFFSET|GROUP\\s+BY|HAVING)\\b',
            'go' => '\\b(?:ORDER\\s+BY|LIMIT|OFFSET|GROUP\\s+BY|HAVING)\\b'
        ],
        [
            'key' => 'sql_transaction',
            'label' => 'SQL Transaction',
            'php' => '\\b(?:START TRANSACTION|COMMIT|ROLLBACK|BEGIN)\\b',
            'go' => '\\b(?:START TRANSACTION|COMMIT|ROLLBACK|BEGIN)\\b'
        ],
        [
            'key' => 'sql_query_time',
            'label' => 'SQL Query Time',
            'php' => '(?:total time|query time|slow query|long query): [0-9.]+ ms',
            'go' => '(?:total time|query time|slow query|long query): [0-9.]+ ms'
        ],
        [
            'key' => 'sql_error',
            'label' => 'SQL Error',
            'php' => '(?:SQLSTATE|Syntax error or access violation|Table \'.*\' doesn\'t exist|You have an error in your SQL syntax|sql: .*)',
            'go' => '(?:SQLSTATE|Syntax error or access violation|Table \'.*\' doesn\'t exist|You have an error in your SQL syntax|sql: .*)'
        ],
        [
            'key' => 'sql_connection_error',
            'label' => 'SQL Connection Error',
            'php' => '(?:SQLSTATE\\[HY000\\] \\[2002\\] Connection refused|(?:connection refused|failed to connect|dial tcp .*: i/o timeout))',
            'go' => '(?:SQLSTATE\\[HY000\\] \\[2002\\] Connection refused|(?:connection refused|failed to connect|dial tcp .*: i/o timeout))'
        ],
        [
            'key' => 'sql_deadlock',
            'label' => 'SQL Deadlock',
            'php' => '(?:Deadlock found when trying to get lock|database is locked|deadlock: .*)',
            'go' => '(?:Deadlock found when trying to get lock|database is locked|deadlock: .*)'
        ],
        [
            'key' => 'sql_query_type',
            'label' => 'SQL Query Type',
            'php' => '\\b(?:SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE)\\b',
            'go' => '\\b(?:SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE)\\b'
        ],
        [
            'key' => 'sql_mysql_log_level',
            'label' => 'MySQL Log Level',
            'php' => '\\[(?:Note|Warning|Error|ERROR|WARNING)\\]',
            'go' => '\\[(?:Note|Warning|Error|ERROR|WARNING)\\]'
        ],
        [
            'key' => 'sql_mysql_error_code',
            'label' => 'MySQL Error Code',
            'php' => '\\[MY-[0-9]+\\]',
            'go' => '\\[MY-[0-9]+\\]'
        ],
        // HTTP Group
        [
            'key' => 'http_method',
            'label' => 'HTTP Method',
            'php' => '\\b(?:GET|POST|PUT|DELETE|PATCH|OPTIONS|HEAD|TRACE|CONNECT)\\b',
            'go' => '\\b(?:GET|POST|PUT|DELETE|PATCH|OPTIONS|HEAD|TRACE|CONNECT)\\b'
        ],
        [
            'key' => 'http_status_success',
            'label' => 'HTTP Status Success (2xx)',
            'php' => '\\b2[0-9]{2}\\b',
            'go' => '\\b2[0-9]{2}\\b'
        ],
        [
            'key' => 'http_status_redirect',
            'label' => 'HTTP Status Redirect (3xx)',
            'php' => '\\b3[0-9]{2}\\b',
            'go' => '\\b3[0-9]{2}\\b'
        ],
        [
            'key' => 'http_status_error',
            'label' => 'HTTP Status Error (4xx/5xx)',
            'php' => '\\b[45][0-9]{2}\\b',
            'go' => '\\b[45][0-9]{2}\\b'
        ],
        [
            'key' => 'grpc_error',
            'label' => 'gRPC Error',
            'php' => 'rpc error: code = [^\\s]+ desc = .*',
            'go' => 'rpc error: code = [^\\s]+ desc = .*'
        ],
        [
            'key' => 'http_upstream_timeout',
            'label' => 'HTTP Upstream Timeout',
            'php' => 'upstream timed out',
            'go' => 'upstream timed out'
        ],
        [
            'key' => 'http_version',
            'label' => 'HTTP Version',
            'php' => 'HTTP/[0-9]\\.[0-9]',
            'go' => 'HTTP/[0-9]\\.[0-9]'
        ],
        [
            'key' => 'url_http_https',
            'label' => 'URL (http/https)',
            'php' => 'https?://[-a-zA-Z0-9@:%._\\+~#=]{1,256}(?::[0-9]{1,5})?\\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&/\\=]*)?',
            'go' => 'https?://[-a-zA-Z0-9@:%._\\+~#=]{1,256}(?::[0-9]{1,5})?\\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&/\\=]*)?'
        ],
        [
            'key' => 'http_request_uri',
            'label' => 'HTTP Request URI',
            'php' => '\\b(?:GET|POST|PUT|DELETE|PATCH) ([^\\s]+) HTTP/',
            'go' => '\\b(?:GET|POST|PUT|DELETE|PATCH) ([^\\s]+) HTTP/'
        ],
        [
            'key' => 'http_referrer',
            'label' => 'HTTP Referrer',
            'php' => 'referrer: "https?://.*"|"(?:http|https)://[^"]+"',
            'go' => 'referrer: "https?://.*"|"(?:http|https)://[^"]+"'
        ],
        [
            'key' => 'user_agent',
            'label' => 'User Agent',
            'php' => '"Mozilla/[^"]+"',
            'go' => '"Mozilla/[^"]+"'
        ],
        [
            'key' => 'http_host',
            'label' => 'HTTP Host',
            'php' => '\\b(?:host|Host)[:=][\\s]?([a-zA-Z0-9.-]+)\\b',
            'go' => '\\b(?:host|Host)[:=][\\s]?([a-zA-Z0-9.-]+)\\b'
        ],
        [
            'key' => 'http_header',
            'label' => 'HTTP Header',
            'php' => '\\b(?:X-[a-zA-Z0-9-]+|Content-Type|Authorization|Accept|Origin|User-Agent|Referer|Host|Connection|Accept-Encoding|Accept-Language): .*',
            'go' => '\\b(?:X-[a-zA-Z0-9-]+|Content-Type|Authorization|Accept|Origin|User-Agent|Referer|Host|Connection|Accept-Encoding|Accept-Language): .*'
        ],
        [
            'key' => 'http_content_type',
            'label' => 'HTTP Content-Type',
            'php' => 'Content-Type: (?:application/json|text/html|application/xml|text/plain|application/x-www-form-urlencoded|multipart/form-data)',
            'go' => 'Content-Type: (?:application/json|text/html|application/xml|text/plain|application/x-www-form-urlencoded|multipart/form-data)'
        ],
        [
            'key' => 'upstream_address',
            'label' => 'Upstream Address',
            'php' => 'upstream: "(?:fastcgi|http|https|unix):[^"]+"',
            'go' => 'upstream: "(?:fastcgi|http|https|unix):[^"]+"'
        ],
        [
            'key' => 'framework_route',
            'label' => 'Framework Route',
            'php' => '(?:Matched route ".*"|http: panic serving .*)',
            'go' => '(?:Matched route ".*"|http: panic serving .*)'
        ],
        // Infrastructure / System
        [
            'key' => 'log_level',
            'label' => 'Log Level',
            'php' => '\\b(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\\b',
            'go' => '\\b(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\\b'
        ],
        [
            'key' => 'log_channel',
            'label' => 'Log Channel',
            'php' => '\\b(?:app|request|doctrine|security|php|messenger)\\.(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\\b',
            'go' => '\\b(?:app|request|doctrine|security|php|messenger)\\.(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\\b'
        ],
        [
            'key' => 'supervisor_status',
            'label' => 'Supervisor Status',
            'php' => 'entered (?:RUNNING|STOPPED|FATAL|BACKOFF|STOPPING|EXITED) state',
            'go' => 'entered (?:RUNNING|STOPPED|FATAL|BACKOFF|STOPPING|EXITED) state'
        ],
        [
            'key' => 'cron_task',
            'label' => 'Cron Task',
            'php' => '(?:CRON\\[[0-9]+\\]: .*|cron: .*)',
            'go' => '(?:CRON\\[[0-9]+\\]: .*|cron: .*)'
        ],
        [
            'key' => 'kernel_message',
            'label' => 'Kernel Message',
            'php' => 'kernel: \\[?[^\\]]+\\]? .*',
            'go' => 'kernel: \\[?[^\\]]+\\]? .*'
        ],
        [
            'key' => 'process_id',
            'label' => 'Process ID (PID)',
            'php' => '\\b(?:pid|PID)[:\\s]?\\[?[0-9]+\\]?\\b',
            'go' => '\\b(?:pid|PID)[:\\s]?\\[?[0-9]+\\]?\\b'
        ],
        [
            'key' => 'memory_usage',
            'label' => 'Memory Usage',
            'php' => '[0-9]+ (?:B|KB|MB|GB|TB)',
            'go' => 'Alloc = [0-9]+ TotalAlloc = [0-9]+ Sys = [0-9]+ NumGC = [0-9]+'
        ],
        [
            'key' => 'memory_limit',
            'label' => 'Memory Limit',
            'php' => '(?:Allowed memory size of [0-9]+ bytes exhausted|runtime: out of memory)',
            'go' => '(?:Allowed memory size of [0-9]+ bytes exhausted|runtime: out of memory)'
        ],
        [
            'key' => 'runtime_version',
            'label' => 'Runtime Version',
            'php' => '(?:PHP [0-9]+\\.[0-9]+\\.[0-9]+|go version go[0-9]+\\.[0-9]+\\.[0-9]+)',
            'go' => '(?:PHP [0-9]+\\.[0-9]+\\.[0-9]+|go version go[0-9]+\\.[0-9]+\\.[0-9]+)'
        ],
        [
            'key' => 'environment',
            'label' => 'Environment',
            'php' => '\\b(?:env|environment)[:=](dev|prod|staging|test)\\b',
            'go' => '\\b(?:env|environment)[:=](dev|prod|staging|test)\\b'
        ],
        [
            'key' => 'extension_module',
            'label' => 'Extension / Module',
            'php' => '(?:extension ".*" is missing|module .* not found)',
            'go' => '(?:extension ".*" is missing|module .* not found)'
        ],
        [
            'key' => 'system_sudo_session',
            'label' => 'System Sudo Session',
            'php' => 'sudo: pam_unix\\(sudo:session\\): session (?:opened|closed)',
            'go' => 'sudo: pam_unix\\(sudo:session\\): session (?:opened|closed)'
        ],
        [
            'key' => 'kernel_interface_state',
            'label' => 'Kernel Interface State',
            'php' => '\\b(?:veth[a-zA-Z0-9]+|eth[0-9]+|br-[a-zA-Z0-9]+)\\b.*entered (?:disabled|blocking|forwarding|disabled) state',
            'go' => '\\b(?:veth[a-zA-Z0-9]+|eth[0-9]+|br-[a-zA-Z0-9]+)\\b.*entered (?:disabled|blocking|forwarding|disabled) state'
        ],
        // Networking
        [
            'key' => 'ipv4_address',
            'label' => 'IPv4 Address',
            'php' => '\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b',
            'go' => '\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b'
        ],
        [
            'key' => 'ip_with_port',
            'label' => 'IP with Port',
            'php' => '\\b(?:\\d{1,3}\\.){3}\\d{1,3}:\\d{1,5}\\b',
            'go' => '\\b(?:\\d{1,3}\\.){3}\\d{1,3}:\\d{1,5}\\b'
        ],
        [
            'key' => 'ipv6_address',
            'label' => 'IPv6 Address',
            'php' => '(?<![a-zA-Z0-9_])(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?::[0-9a-fA-F]{1,4}){1,6}|:(?::[0-9a-fA-F]{1,4}){1,7}|::)(?![a-zA-Z0-9_])',
            'go' => '\\b(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?::[0-9a-fA-F]{1,4}){1,6}|:(?::[0-9a-fA-F]{1,4}){1,7}|::)\\b'
        ],
        [
            'key' => 'ipv6_with_port',
            'label' => 'IPv6 with Port',
            'php' => '\\[(?:(?:[0-9a-fA-F]{1,4}:){1,7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?::[0-9a-fA-F]{1,4}){1,6}|:(?::[0-9a-fA-F]{1,4}){1,7}|::)\\]:\\d{1,5}',
            'go' => '\\[(?:(?:[0-9a-fA-F]{1,4}:){1,7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?::[0-9a-fA-F]{1,4}){1,6}|:(?::[0-9a-fA-F]{1,4}){1,7}|::)\\]:\\d{1,5}'
        ],
        [
            'key' => 'mac_address',
            'label' => 'MAC Address',
            'php' => '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$',
            'go' => '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$'
        ],
        // Data / Formatting
        [
            'key' => 'json_block',
            'label' => 'JSON Block',
            'php' => '\\{(?:[^{}]|(?R))*\\}|\\[(?:[^\\[\\]]|(?R))*\\]',
            'go' => '\\{.*?\\}|\\[.*?\\]'
        ],
        [
            'key' => 'json_sql_data',
            'label' => 'JSON SQL Data',
            'php' => '\\{"sql": ".*", "params": \\[.*\\]\\}',
            'go' => '\\{"sql": ".*", "params": \\[.*\\]\\}'
        ],
        [
            'key' => 'quoted_string',
            'label' => 'Quoted String',
            'php' => '(?:"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\')',
            'go' => '(?:"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\')'
        ],
        [
            'key' => 'hex_color',
            'label' => 'Hex Color',
            'php' => '^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$',
            'go' => '^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$'
        ],
        [
            'key' => 'uuid_guid',
            'label' => 'UUID / GUID',
            'php' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
            'go' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'
        ],
        [
            'key' => 'email_address',
            'label' => 'Email Address',
            'php' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}',
            'go' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}'
        ],
        [
            'key' => 'phone_number',
            'label' => 'Phone Number',
            'php' => '(?:\\+|00)\\d{1,3}(?:[\\s.-]?\\d{1,4}){2,5}|\\b\\d{3}[\\s.-]?\\d{3}[\\s.-]?\\d{4}\\b',
            'go' => '(?:\\+|00)\\d{1,3}(?:[\\s.-]?\\d{1,4}){2,5}|\\b\\d{3}[\\s.-]?\\d{3}[\\s.-]?\\d{4}\\b'
        ],
        [
            'key' => 'credit_card_mask',
            'label' => 'Credit Card (Masked)',
            'php' => '\\b(?:\\d{4}[\\s.-]?[X*]{4}[\\s.-]?[X*]{4}[\\s.-]?\\d{4}|[X*]{12,15}\\d{4})\\b',
            'go' => '\\b(?:\\d{4}[\\s.-]?[X*]{4}[\\s.-]?[X*]{4}[\\s.-]?\\d{4}|[X*]{12,15}\\d{4})\\b'
        ],
        [
            'key' => 'sensitive_data_cc',
            'label' => 'Sensitive Data (CC)',
            'php' => '\\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}|(?:2131|1800|35\\d{3})\\d{11})\\b',
            'go' => '\\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}|(?:2131|1800|35\\d{3})\\d{11})\\b'
        ],
        // Time
        [
            'key' => 'iso_8601_date',
            'label' => 'ISO 8601 Date',
            'php' => '\\d{4}-\\d{2}-\\d{2}(?:T|\\s)\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:?\\d{2})?',
            'go' => '\\d{4}-\\d{2}-\\d{2}(?:T|\\s)\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:?\\d{2})?'
        ],
        [
            'key' => 'timestamp_brackets',
            'label' => 'Timestamp in Brackets',
            'php' => '\\[\\d{4}-\\d{2}-\\d{2}[T\\s]\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:\\[+-]\\d{2}:?\\d{2}|Z)?\\]',
            'go' => '\\[\\d{4}-\\d{2}-\\d{2}[T\\s]\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:\\[+-]\\d{2}:?\\d{2}|Z)?\\]'
        ],
        [
            'key' => 'unix_timestamp',
            'label' => 'Unix Timestamp',
            'php' => '\\b\\d{10}(?:\\.\\d+)?\\b',
            'go' => '\\b\\d{10}(?:\\.\\d+)?\\b'
        ],
        [
            'key' => 'time_duration',
            'label' => 'Time Duration',
            'php' => '[0-9]+(?:\\.[0-9]+)?(?:ms|s)',
            'go' => '[0-9]+(?:\\.[0-9]+)?(?:ns|µs|ms|s|m|h)'
        ],
        // Others
        [
            'key' => 'file_path',
            'label' => 'File Path',
            'php' => '(?:[a-zA-Z]:\\\\|/)[^\\s:?*"<>|]+',
            'go' => '(?:[a-zA-Z]:\\\\|/)[^\\s:?*"<>|]+'
        ],
        [
            'key' => 'request_id',
            'label' => 'Request ID',
            'php' => '\\b(?:req-|request_id[:=]|X-Request-ID: )([a-zA-Z0-9-]+)\\b',
            'go' => '\\b(?:req-|request_id[:=]|X-Request-ID: )([a-zA-Z0-9-]+)\\b'
        ],
        [
            'key' => 'auth_user',
            'label' => 'Auth / User ID',
            'php' => '\\b(?:user_id|uid)[:=][0-9]+\\b',
            'go' => '\\b(?:user_id|uid)[:=][0-9]+\\b'
        ]
    ];

    public function __construct(private ConfigurationProvider $configurationProvider) {}

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public function getTemplates(): array
    {
        $templates = [];
        $isGo = $this->configurationProvider->parserGoEnabled;

        foreach (self::SPECIFIC_TEMPLATES as $item) {
            $templates[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'value' => $isGo ? $item['go'] : $item['php']
            ];
        }

        return $templates;
    }
}
