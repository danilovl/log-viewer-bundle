<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration\Command;

use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\LogNotifier;
use Danilovl\LogViewerBundle\Command\LogWatcherCommand;
use Danilovl\LogViewerBundle\Event\LogWatcherBeforeNotifyEvent;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

class LogWatcherCommandTest extends KernelTest
{
    private string $tempLogDir;

    private string $logFile;

    private LogViewer $logViewer;

    private LogNotifier $logNotifier;

    protected function setUp(): void
    {
        $this->tempLogDir = sys_get_temp_dir() . '/log_watcher_test_' . uniqid();
        mkdir($this->tempLogDir, 0o777, true);

        $this->logFile = $this->tempLogDir . '/test.log';
        file_put_contents($this->logFile, "Initial line\n");

        $this->bootKernel([
            'sources' => [
                'files' => [$this->logFile]
            ],
            'live_log_page' => [
                'enabled' => true
            ],
            'parser' => [
                'default' => 'monolog'
            ]
        ]);

        /** @var LogViewer $logViewer */
        $logViewer = $this->container->get(LogViewer::class);
        $this->logViewer = $logViewer;

        /** @var LogNotifier $logNotifier */
        $logNotifier = $this->container->get(LogNotifier::class);
        $this->logNotifier = $logNotifier;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        if (is_dir($this->tempLogDir)) {
            rmdir($this->tempLogDir);
        }
    }

    public function testCommandInvokeOnce(): void
    {
        /** @var LogWatcherCommand $command */
        $command = $this->container->get(LogWatcherCommand::class);
        $output = new BufferedOutput;

        file_put_contents($this->logFile, "New log line for watcher\n", FILE_APPEND);

        $command($output, interval: 0, limit: 1, limitTime: 1);

        $this->assertStringContainsString('Finished log watching', $output->fetch());
    }

    public function testCommandCancelNotify(): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);

        $eventDispatcher->addListener(LogWatcherBeforeNotifyEvent::class, static function (LogWatcherBeforeNotifyEvent $event): void {
            $event->stopPropagation();
        });

        $output = new BufferedOutput;
        file_put_contents($this->logFile, "Initial line\nNew log line for cancel test\n");

        /** @var LogWatcherCommand $command */
        $command = $this->container->get(LogWatcherCommand::class);

        $command($output, interval: 0, limit: 100, limitTime: 1);

        $fetchOutput = $output->fetch();
        $this->assertStringContainsString('Finished log watching', $fetchOutput);
    }

    public function testCommandUniqueUserIdentifier(): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);

        $command1 = $this->container->get(LogWatcherCommand::class);
        $command2 = new LogWatcherCommand($this->logViewer, $this->logNotifier, $eventDispatcher);

        $ref = new ReflectionClass(LogWatcherCommand::class);
        $prop = $ref->getProperty('userIdentifier');

        $id1 = $prop->getValue($command1);
        $id2 = $prop->getValue($command2);

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('log-viewer-watcher-', $id1);
    }
}
