[![phpunit](https://github.com/danilovl/log-viewer-bundle/actions/workflows/phpunit.yml/badge.svg)](https://github.com/danilovl/log-viewer-bundle/actions/workflows/phpunit.yml)
[![downloads](https://img.shields.io/packagist/dt/danilovl/log-viewer-bundle)](https://packagist.org/packages/danilovl/log-viewer-bundle)
[![latest Stable Version](https://img.shields.io/packagist/v/danilovl/log-viewer-bundle)](https://packagist.org/packages/danilovl/log-viewer-bundle)
[![license](https://img.shields.io/packagist/l/danilovl/log-viewer-bundle)](https://packagist.org/packages/danilovl/log-viewer-bundle)

# LogViewerBundle

This Symfony bundle provides a user-friendly interface and API for viewing application logs (Monolog).
It supports log reading through standard PHP methods as well as a high-performance Go-based parser.

## Features

- **Advanced Search**: Support for regular expressions (Regex), case-sensitivity toggles, and search term highlighting in the search bar.
- **Global Search**: Search across multiple log files at once from a dedicated page with combined and time-sorted results.
- **Live Multi-source View**: Select multiple log files in the Live Log view via a modal to monitor them simultaneously in a single real-time stream.
- **Zen Mode**: Fullscreen distraction-free mode that hides the sidebar and header, perfect for monitoring logs on a dedicated display.
- **Bookmarks**: Quickly mark specific log entries with a "star" and filter to see only your bookmarked items. Bookmarks are persisted in your browser's local storage.
- **Modern Dashboard**: Vue 3 based Single Page Application (SPA) for a smooth user experience.
- **Log Viewing**: Advanced filtering, search, and pagination for historical logs.
- **Live Log View**: Real-time log streaming with automatic updates and level-based filtering.
- **Multiple Parsers**: Support for Monolog, Nginx, Apache, PHP, Syslog, and more.
- **High Performance**: Optional Go-based parser for extremely fast processing of large log files.
- **Log Statistics**: Detailed charts and metrics (by level, channel, timeline) with interactive range selection powered by Google Charts.
- **Auto-Refresh**: Configurable real-time updates for dashboard and individual log views.
- **AI-Powered Analysis**: Quick "Ask AI" button for error logs, supporting ChatGPT, Perplexity, Gemini, Claude, and more.
- **Dark & Light Modes**: Full support for dark and light themes, respecting system preferences or manual toggle.
- **Multilingual**: Supports 10+ languages (English, Russian, Chinese, Hindi, Spanish, French, Arabic, Portuguese, Japanese, German).
- **Remote Logs**: View logs from remote servers via SSH/SFTP.
- **REST API**: Full-featured API for integration and custom dashboard development.
- **Log Notifications**: Real-time notifications for log entries matching specific rules via Symfony Notifier.
- **File Management**: Ability to delete log files directly from the dashboard.
- **Ignore Files**: Flexible exclusion of specific log files or directories.

## Screenshots

Explore the user interface and features of the Log Viewer:

![Dashboard](/readme/dashboard.png?raw=true "Dashboard page")

![Live logs](/readme/live-logs-stream.png?raw=true "Live logs")

![Logs](/readme/logs.png?raw=true "Logs")

![Log dark mode](/readme/logs-dark-mode.png?raw=true "Log dark mode")

![Global search](/readme/global-search.png?raw=true "Global-search")

![Bookmarks](/readme/bookmarks.png?raw=true "Bookmarks")

![Features](/readme/features.png?raw=true "Features")

## Requirements

- PHP 8.5+
- Symfony 8.0+

## Installation

1. Install the package via Composer:

```bash
composer require danilovl/log-viewer-bundle
```

2. Register the bundle in `config/bundles.php` (if not done automatically):

```php
return [
    // ...
    Danilovl\LogViewerBundle\LogViewerBundle::class => ['all' => true],
];
```

3. Run the following command to install the bundle assets:

```bash
php bin/console assets:install
```

4. Include the routes in `config/routes.yaml`:

```yaml
_danilovl_log_viewer:
    resource: "@LogViewerBundle/Resources/config/routing.yaml"
```

## Configuration

Create a configuration file `config/packages/danilovl_log_viewer.yaml`.

### 1. Full Configuration Reference
This example shows all available configuration keys with their structure and default values.

```yaml
danilovl_log_viewer:
    # Source settings
    sources:
        # Directories to search for .log files (default: ['%kernel.logs_dir%'])
        dirs: ['%kernel.logs_dir%']
        # Individual log files (default: [])
        files: []
        # Log files to ignore (supports filenames or full paths) (default: [])
        ignore: []
        # Max file size to be read in bytes (default: null, read entire file)
        max_file_size: null
        # Allow log file deletion from the dashboard (default: false)
        allow_delete: false
        # Allow log file download from the dashboard (default: false)
        allow_download: false
        # Remote hosts configuration (default: [])
        remote_hosts:
            - name: ~ # Required: Unique name for this remote host
              type: 'ssh' # Connection type (ssh, sftp, http)
              host: ~ # Required: Remote host address
              port: 22
              user: ~
              password: ~
              ssh_key: ~ # Path to the SSH private key
              max_file_size: null # Max file size for this remote host
              dirs: []
              files: []
              ignore: []

    # Parser settings
    parser:
        # Default parser for all files (default: null)
        default: null
        # Parser overrides for specific files (default: [])
        # Key is the absolute file path, value is the parser type.
        overrides: []
        # Enable Go-based parser for high performance (default: false)
        go_enabled: false
        # Path to the Go parser binary
        go_binary_path: '%kernel.project_dir%/vendor/danilovl/log-viewer-bundle/bin/dist/go-parser'

    # Cache settings
    cache:
        # Enable caching for auto-detected parser types (default: false)
        parser_detect_enabled: false
        # Enable caching for log statistics (default: true)
        statistic_enabled: true
        # Cache interval (e.g., "5 sec", "1 minute")
        statistic_interval: '5 sec'

    # Dashboard page settings
    dashboard_page:
        # Enable statistics for dashboard (default: false)
        statistic_enabled: false
        # Enable auto-refresh (default: false)
        auto_refresh_enabled: false
        # Auto-refresh interval (default: '1 minute')
        auto_refresh_interval: '1 minute'
        # Show countdown for auto-refresh (default: false)
        auto_refresh_show_countdown: false

    # Live log page settings
    live_log_page:
        # Enable live log page (default: false)
        enabled: false
        # Live update interval (default: '5 sec')
        interval: '5 sec'
        # Log levels to show in live update (default: [])
        # Supported: emergency, alert, critical, error, warning, notice, info, debug
        levels: []
        # Override sources for live log page
        sources:
            dirs: []
            files: []
            ignore: []
            remote_hosts: []

    # Log detail page settings
    log_page:
        # Enable statistics for individual log files (default: false)
        statistic_enabled: false
        # Enable auto-refresh (default: false)
        auto_refresh_enabled: false
        # Auto-refresh interval (default: '5 sec')
        auto_refresh_interval: '5 sec'
        # Show countdown for auto-refresh (default: false)
        auto_refresh_show_countdown: false
        # Entries limit (default: 50)
        limit: 50

    # AI Integration settings
    ai:
        # Log levels for which "Ask AI" button appears on log entries (default: [])
        # Supported: emergency, alert, critical, error, warning, notice, info, debug
        button_levels: []
        # Custom AI chats configuration (default: presets for ChatGPT, Perplexity, Gemini, Claude, DeepSeek)
        chats:
            - name: 'ChatGPT'
              url: 'https://chatgpt.com/?q={prompt}'
              has_prompt: true
            - name: 'Google Search'
              url: 'https://www.google.com/search?q={prompt}'
              has_prompt: false

    # Custom API prefix for bundle routes (default: '/danilovl/log-viewer/api')
    api_prefix: '/danilovl/log-viewer/api'
    
    # Webpack Encore build name (leave null for default)
    encore_build_name: null

    # Log Notification settings
    notifier:
        # Enable notifications (default: false)
        enabled: false
        # Notification rules (default: [])
        rules:
            - name: 'Critical Errors'
              # Log levels for which this rule applies (default: [])
              levels: ['critical', 'error', 'emergency']
              # Keywords that the log entry must contain (default: [])
              contains: ['Fatal error', 'Database connection failed']
              # Notifier channels (default: [])
              # Available channels: chat/slack, chat/telegram, email
              channels: ['chat/slack']
```

### 2. Real-world Configuration Example
A practical example for a typical production environment.

```yaml
danilovl_log_viewer:
    sources:
        dirs:
            - '%kernel.logs_dir%'
            - '/var/log/nginx'
        files:
            - '/var/log/syslog'
        allow_delete: true
        allow_download: true
        max_file_size: 20971520 # 20 MB
        remote_hosts:
            - name: 'prod_server'
              type: 'ssh'
              host: '1.2.3.4'
              user: 'deploy'
              ssh_key: '%kernel.project_dir%/config/ssh/id_rsa'
              dirs: ['/var/www/app/var/log']
              files: ['/var/log/php8.2-fpm.log']
              ignore: ['cache.log']
    
    parser:
        go_enabled: true
        overrides:
            '/var/log/nginx/access.log': 'nginx_access'
            '/var/log/nginx/error.log': 'nginx_error'
            '/var/log/syslog': 'syslog'

    dashboard_page:
        statistic_enabled: true
        auto_refresh_enabled: true

    log_page:
        statistic_enabled: true
        auto_refresh_enabled: true
        auto_refresh_show_countdown: true
        limit: 100

    live_log_page:
        enabled: true
        interval: '3 sec'
        levels: ['error', 'critical', 'emergency']
        sources:
            dirs: ['%kernel.logs_dir%/important']
            files: ['%kernel.logs_dir%/custom.log']
            ignore: ['ignore_this.log']
            remote_hosts:
                - { name: 'prod-server', host: '1.2.3.4', files: ['/var/log/app.log'] }

    ai:
        button_levels: [error, critical, alert, emergency]
        chats:
            - { name: 'ChatGPT', url: 'https://chatgpt.com/?q={prompt}', has_prompt: true }
            - { name: 'Perplexity', url: 'https://www.perplexity.ai/?q={prompt}', has_prompt: true }
            - { name: 'Gemini', url: 'https://gemini.google.com/app?q={prompt}', has_prompt: true }
            - { name: 'Claude', url: 'https://claude.ai/new?q={prompt}', has_prompt: true }
            - { name: 'DeepSeek', url: 'https://chat.deepseek.com/?q={prompt}', has_prompt: true }

    notifier:
        enabled: true
        rules:
            - name: 'App Critical'
              levels: ['critical', 'error', 'emergency']
              channels: ['chat/slack', 'email']
```

Note: for `ssh` and `sftp` types, the `ssh2` PHP extension is required.

## Log Notifications (Watcher)

To enable real-time notifications for new log entries matching your rules, you need to run the watcher command in the background.

```bash
php bin/console danilovl:log-viewer:watch --interval=5 --limit=100
```

- `--interval` (`-i`): Polling interval in seconds (default: 5).
- `--limit` (`-l`): Maximum number of new entries to process per file in each poll (default: 100).

The watcher uses the `symfony/notifier` component. Make sure you have configured your [Notifier transports](https://symfony.com/doc/current/notifier.html) in your Symfony application.

### Configuring Notifier Channels

To use the `channels` defined in your configuration, you need to configure the corresponding transports in your Symfony application's `config/packages/framework.yaml`.

#### 1. Slack Example
Install the Slack notifier: `composer require symfony/slack-notifier`
```yaml
# config/packages/framework.yaml
framework:
    notifier:
        chatter_transports:
            slack: '%env(SLACK_DSN)%'
```
Channel in rule: `chat/slack`

#### 2. Telegram Example
Install the Telegram notifier: `composer require symfony/telegram-notifier`
```yaml
# config/packages/framework.yaml
framework:
    notifier:
        chatter_transports:
            telegram: '%env(TELEGRAM_DSN)%'
```
Channel in rule: `chat/telegram`

#### 3. Email Example
Make sure the `mailer` component is configured:
```yaml
# config/packages/framework.yaml
framework:
    notifier:
        texter_transports:
            # If you want to use texter for email/sms
        channel_policy:
            email: ['email']
```
Note: For email notifications to work through the Notifier, you might also need to set up a `channel_policy` or ensure your `AdminNotification` logic (if customized) supports it. By default, the bundle sends a standard `Notification` with the specified channels.

Refer to the official [Symfony Notifier documentation](https://symfony.com/doc/current/notifier.html) for detailed transport configuration (DSN) for each provider.

## Built-in Parsers

The following parsers are available by default:
- `monolog` - Standard Monolog format (usually auto-detected for Symfony logs).
- `json` - Logs in JSON format (one object per line).
- `nginx_access` - Nginx access logs.
- `nginx_error` - Nginx error logs.
- `apache_access` - Apache access logs.
- `php_error` - PHP error logs.
- `mysql` - MySQL error logs.
- `syslog` - Traditional Syslog.
- `modern_syslog` - Modern Syslog format.
- `doctrine` - Doctrine DBAL logs.
- `access` - Generic access logs.
- `supervisord` - Supervisord logs.

## Usage (Dashboard)

After installation and route configuration, the dashboard will be available at: `/danilovl/log-viewer`

### User Interface Features

- **Modern SPA**: The dashboard is a Single Page Application built with Vue 3 and Pinia for fast, reactive navigation.
- **Theme Support**: Includes both Light and Dark themes. The theme can be toggled manually or follow system preferences.
- **Localization**: Supported languages include English, Russian, Chinese, Hindi, Spanish, French, Arabic, Portuguese, Japanese, and German. You can switch the language directly in the UI.
- **Interactive Statistics**: Real-time charts for log level distribution, channels, and error trends over time. Includes an interactive timeline selector (zoom) to focus on specific periods.
- **Live View**: Dedicated page for watching incoming logs in real-time, perfect for debugging active issues. Features color-coded status for new entries and level filtering.
- **Ask AI**: When viewing error logs, use the "Ask AI" button to quickly analyze the error with your favorite AI chat. You can review and edit the prompt before sending it to ensure no sensitive data is shared. The list of AI chats and their query patterns is fully configurable.
- **Auto-Refresh**: Stay up to date with incoming logs and dashboard statistics using the built-in auto-refresh feature with countdown indicators.

### Development (Assets)

The bundle's assets are pre-built and included in the package. If you want to customize the frontend:

1. Install dependencies:
```bash
docker compose run --rm node npm install
```

2. Build for production:
```bash
docker compose run --rm node npm run build
```

## Cache Clear

To clear the bundle cache (including log statistics and parser detection), run the following command:

```bash
php bin/console danilovl:log-viewer:cache-clear
```

## API Endpoints

All API endpoints are prefixed with `/danilovl/log-viewer/api`.

### 1. Configuration
`GET /config`
Returns the current bundle configuration (available levels, channels, etc.).

### 2. Folder Structure
`GET /structure`
Returns a hierarchical structure of folders and log files.

`GET /folders`
Returns a flat list of folders containing log files with their total count.
Response: `{ "folders": [...], "totalCount": 0 }`

### 3. Log Entries
`GET /entries`
Returns log entries for a specific source with pagination.
Query parameters:
- `sourceId` (string, required): The Source ID to view.
- `limit` (int): Number of entries (default: 50).
- `cursor` (string): Cursor for pagination.
- `offset` (int): Numeric offset.
- `sortDir` (desc|asc): Sorting direction.
- `level` (string): Filter by log level.
- `channel` (string): Filter by channel.
- `search` (string): Search text.

`GET /entries/new`
Returns only new log entries across specified sources (used for real-time updates).
Query parameters:
- `levels` (string): Comma-separated list of log levels to filter (optional).
- `sourceIds` (string): Comma-separated list of Source IDs (optional).

`GET /global-search`
Returns log entries for multiple sources with combined sorting.
Query parameters:
- `sourceId` (string, required): Comma-separated list of Source IDs.
- `limit` (int): Number of entries.
- `offset` (int): Numeric offset.
- `sortDir` (desc|asc): Sorting direction.
- `level` (string): Filter by log level.
- `channel` (string): Filter by channel.
- `search` (string): Search text.
- `searchRegex` (bool): Enable regex search.
- `searchCaseSensitive` (bool): Enable case-sensitive search.

`GET /entries-count`
Returns the total count of entries for the given filters.
Query parameters:
- `sourceId` (string, required): The Source ID.
- `level` (string): Filter by log level.
- `channel` (string): Filter by channel.
- `search` (string): Search text.

Response: `{ "totalCount": 0 }`

### 4. Statistics
`GET /stats`
Returns statistics for a specific file (total count, distribution by levels and channels).
Query parameters:
- `sourceId` (string, required): The Source ID.
- `level` (string): Filter by log level.
- `channel` (string): Filter by channel.
- `search` (string): Search text.
- `timelineFormat` (string): Format for timeline (minute, hour, day).

`GET /dashboard-stats`
Summary statistics across all sources for the dashboard.
Query parameters:
- `timelineFormat` (string): Format for timeline (minute, hour, day).

### 5. File Management
`DELETE /delete`
Deletes a specific log file.
Query parameters:
- `sourceId` (string, required): The Source ID.

`GET /download`
Downloads a specific log file.
Query parameters:
- `sourceId` (string, required): The Source ID.

## Events

The bundle provides several events that allow you to customize its behavior without modifying the core logic. You can use these events to filter logs, change file permissions, or modify log entries.

### 1. LogViewerDataEvent
Dispatched when collecting data for both folder structure and flat file list.
- **Event Class**: `Danilovl\LogViewerBundle\Event\LogViewerDataEvent`
- **Properties**:
    - `structure` (`list<LogViewerFolderStructure>`): The hierarchical structure of log sources.
- **Use case**: Filter out specific folders or files from the list or modify their properties (like `canDelete`, `canDownload`) globally for the entire application. Modifying the `structure` property directly affects what is shown on the dashboard and sidebar.

### 2. LogViewerEntriesEvent
Dispatched after fetching log entries for a specific file, but before they are sent to the frontend.
- **Event Class**: `Danilovl\LogViewerBundle\Event\LogViewerEntriesEvent`
- **Use case**: Modify log entries (e.g., anonymize sensitive data or add extra metadata).

### 3. LogViewerDownloadEvent & LogViewerDeleteEvent
Dispatched before downloading or deleting a log file.
- **Event Classes**:
    - `Danilovl\LogViewerBundle\Event\LogViewerDownloadEvent`
    - `Danilovl\LogViewerBundle\Event\LogViewerDeleteEvent`
- **Use case**: Stop the propagation of the event to block the action based on custom security rules.

Example of an Event Subscriber:
```php
use Danilovl\LogViewerBundle\Event\LogViewerDownloadEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LogViewerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogViewerDownloadEvent::class => 'onDownload'
        ];
    }

    public function onDownload(LogViewerDownloadEvent $event): void
    {
        $source = $event->source;
        // Logic to check if the user can download this file
        if ($source->name === 'security.log') {
            $event->stopPropagation();
        }
    }
}
```

## Custom Log Parsers

You can create your own log parser if your logs are not in the standard Monolog format.

### 1. Create a Parser Class

Implement the `Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser`:

```php
namespace App\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Interfaces\LogParserGoPatternInterface;

class MyCustomParser implements LogInterfaceParser, LogParserGoPatternInterface
{
    private const string PATTERN = '/(?P<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (?P<level>\w+) (?P<message>.*)/';

    public function parse(string $line, string $filename): LogEntry
    {
        preg_match(self::PATTERN, $line, $matches);

        return new LogEntry(
            timestamp: $matches['timestamp'] ?? '',
            level: $matches['level'] ?? 'INFO',
            channel: 'custom',
            message: $matches['message'] ?? $line,
            file: $filename,
            context: []
        );
    }

    public function getName(): string
    {
        return 'my_custom_format';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'my_custom_format';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'custom';
    }

    public function getGoPattern(?string $parserType): string
    {
        return '(?P<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (?P<level>\w+) (?P<message>.*)';
    }
}
```

### 2. Register the Parser

If you use standard Symfony autowiring and autoconfiguration, your parser will be automatically registered and tagged with `danilovl.log_viewer.parser`.

### 3. Use the Parser in Configuration

Update your `danilovl_log_viewer.yaml`:

```yaml
danilovl_log_viewer:
    parser:
        overrides:
            '/path/to/your/custom.log': 'my_custom_format'
```

Or set it as the default:

```yaml
danilovl_log_viewer:
    parser:
        default: 'my_custom_format'
```

## License

The LogViewerBundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
