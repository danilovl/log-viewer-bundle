<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Service;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry
};
use Danilovl\LogViewerBundle\Service\{
    ConfigurationProvider,
    LogNotifier
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\{
    NoRecipient,
    RecipientInterface
};

final class LogNotifierTest extends TestCase
{
    /**
     * @param array<int, array{name: string, levels: string[], contains: string[], channels: string[]}> $rules
     */
    private function createConfigurationProvider(bool $enabled, array $rules = []): ConfigurationProvider
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
            parserGoEnabled: false,
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
            logPageLimit: 50,
            aiButtonLevels: [],
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: $enabled,
            notifierRules: $rules
        );
    }

    private function createLogEntry(string $level, string $message): LogEntry
    {
        return new LogEntry(
            timestamp: '2023-01-01 12:00:00',
            level: $level,
            channel: 'app',
            message: $message,
            file: 'app.log',
            normalizedTimestamp: '2023-01-01 12:00:00'
        );
    }

    public function testNotifyDisabled(): void
    {
        $config = $this->createConfigurationProvider(false);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('send');

        $logNotifier = new LogNotifier($config, $notifier);
        $logNotifier->notify('test', [$this->createLogEntry('error', 'test message')]);
    }

    public function testNotifyMatchesRule(): void
    {
        $rule = ['name' => 'test rule', 'levels' => ['error'], 'contains' => ['important'], 'channels' => ['chat/slack']];
        $config = $this->createConfigurationProvider(true, [$rule]);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(static function (Notification $notification) {
                    return $notification->getSubject() === '[test rule] ERROR log entry detected'
                        && str_contains($notification->getContent(), 'important message')
                        && $notification->getChannels(new NoRecipient) === ['chat/slack'];
                }),
                $this->isInstanceOf(RecipientInterface::class)
            );

        $logNotifier = new LogNotifier($config, $notifier);
        $entry = $this->createLogEntry('error', 'important message');
        $logNotifier->notify('test', [$entry]);
    }

    public function testNotifyDoesNotMatchLevel(): void
    {
        $rule = ['name' => 'test rule', 'levels' => ['critical'], 'contains' => [], 'channels' => ['chat/slack']];
        $config = $this->createConfigurationProvider(true, [$rule]);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('send');

        $logNotifier = new LogNotifier($config, $notifier);
        $entry = $this->createLogEntry('error', 'message');
        $logNotifier->notify('test', [$entry]);
    }

    public function testNotifyDoesNotMatchContent(): void
    {
        $rule = ['name' => 'test rule', 'levels' => [], 'contains' => ['important'], 'channels' => ['chat/slack']];
        $config = $this->createConfigurationProvider(true, [$rule]);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('send');

        $logNotifier = new LogNotifier($config, $notifier);
        $entry = $this->createLogEntry('error', 'regular message');
        $logNotifier->notify('test', [$entry]);
    }
}
