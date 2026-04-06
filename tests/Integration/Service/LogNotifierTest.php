<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration\Service;

use Danilovl\LogViewerBundle\Service\{
    ConfigurationProvider,
    LogNotifier
};
use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class LogNotifierTest extends KernelTest
{
    /** @var LogNotifier */
    private LogNotifier $logNotifier;

    /** @var MockObject&NotifierInterface $notifier */
    private NotifierInterface $notifier;

    protected function setUp(): void
    {
        $this->bootKernel([
            'notifier' => [
                'enabled' => true,
                'rules' => [
                    [
                        'name' => 'Critical errors',
                        'levels' => ['critical'],
                        'contains' => [],
                        'channels' => ['chat/slack']
                    ]
                ]
            ]
        ]);

        $this->notifier = $this->createMock(NotifierInterface::class);

        /** @var ConfigurationProvider $configurationProvider */
        $configurationProvider = $this->container->get(ConfigurationProvider::class);

        $logNotifier = new LogNotifier(
            configurationProvider: $configurationProvider,
            notifier: $this->notifier
        );

        $this->container->set(LogNotifier::class, $logNotifier);

        /** @var LogNotifier $logNotifierService */
        $logNotifierService = $this->container->get(LogNotifier::class);
        $this->logNotifier = $logNotifierService;
    }

    private function createLogEntry(string $level = 'error', string $message = 'Something went wrong'): LogEntry
    {
        return new LogEntry(
            timestamp: '2023-01-01 12:00:00',
            level: $level,
            channel: 'app',
            message: $message,
            file: 'test.log',
            normalizedTimestamp: '2023-01-01 12:00:00'
        );
    }

    public function testNotifyMatchesRule(): void
    {
        $this->notifier
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->callback($this->validateNotification(
                    '[Critical errors] critical log entry detected',
                    'Something is really broken'
                )),
                $this->isInstanceOf(RecipientInterface::class)
            );

        $entry = $this->createLogEntry('critical', 'Something is really broken');

        $this->logNotifier->notify('test_source', [$entry]);
    }

    public function testNotifyWithNoMatches(): void
    {
        $this->notifier
            ->expects($this->never())
            ->method('send');

        $entry = $this->createLogEntry('info', 'Just some info');

        $this->logNotifier->notify('test_source', [$entry]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNotifyWithContainsMatch(): void
    {
        $this->bootKernel([
            'notifier' => [
                'enabled' => true,
                'rules' => [
                    [
                        'name' => 'Contains error',
                        'levels' => [],
                        'contains' => ['CRITICAL_ERROR'],
                        'channels' => ['chat/slack']
                    ]
                ]
            ]
        ]);

        $this->notifier = $this->createMock(NotifierInterface::class);

        /** @var ConfigurationProvider $configurationProvider */
        $configurationProvider = $this->container->get(ConfigurationProvider::class);

        $this->container->set(LogNotifier::class, new LogNotifier(
            configurationProvider: $configurationProvider,
            notifier: $this->notifier
        ));

        /** @var LogNotifier $logNotifierService */
        $logNotifierService = $this->container->get(LogNotifier::class);
        $this->logNotifier = $logNotifierService;

        $this->notifier
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->callback($this->validateNotification(
                    '[Contains error] debug log entry detected',
                    'This is a CRITICAL_ERROR message'
                )),
                $this->isInstanceOf(RecipientInterface::class)
            );

        $entry = $this->createLogEntry('debug', 'This is a CRITICAL_ERROR message');

        $this->logNotifier->notify('test_source', [$entry]);
    }

    private function validateNotification(string $expectedSubject, string $expectedContent): callable
    {
        return static function (Notification $notification) use ($expectedSubject, $expectedContent): bool {
            $subjectMatches = mb_strtolower($notification->getSubject()) === mb_strtolower($expectedSubject);
            $contentMatches = str_contains($notification->getContent(), $expectedContent);

            return $subjectMatches && $contentMatches;
        };
    }
}
