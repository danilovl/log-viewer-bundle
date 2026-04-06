<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Command;

use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\LogNotifier;
use Danilovl\LogViewerBundle\Event\LogWatcherBeforeNotifyEvent;
use Symfony\Component\Console\Attribute\{
    AsCommand,
    Option
};
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'danilovl:log-viewer:watch',
    description: 'Watch log files and send notifications for new entries matching the rules.'
)]
final class LogWatcherCommand
{
    private string $userIdentifier;

    public function __construct(
        private readonly LogViewer $logViewer,
        private readonly LogNotifier $logNotifier,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->userIdentifier = 'log-viewer-watcher-' . uniqid();
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Polling interval in seconds', name: 'interval', shortcut: 'i')]
        int $interval = 5,
        #[Option(description: 'Limit for new entries per file', name: 'limit', shortcut: 'l')]
        int $limit = 100,
        #[Option(description: 'Limit for execution time in seconds', name: 'limit-time', shortcut: 't')]
        ?int $limitTime = null
    ): int {
        $messageInterval = sprintf('Watching logs with interval %d seconds...', $interval);
        $output->writeln($messageInterval);

        $messageUserIdentifier = sprintf('User identifier for this session: %s', $this->userIdentifier);
        $output->writeln($messageUserIdentifier);

        $startTime = time();

        while (true) {
            if ($limitTime !== null && (time() - $startTime) >= $limitTime) {
                $output->writeln('Execution time limit reached. Stopping...');

                break;
            }

            $sources = $this->logViewer->getSources();
            $lastPositions = $this->logViewer->loadWatcherPositions($this->userIdentifier);
            $newPositions = $lastPositions;

            foreach ($sources as $source) {
                if (!$source->isValid) {
                    continue;
                }

                $lastPosition = $lastPositions[$source->id] ?? 0;

                try {
                    $result = $this->logViewer->getNewEntries(
                        filePath: $source->path,
                        parserType: $source->parserType,
                        lastPosition: $lastPosition,
                        host: $source->host,
                        limit: $limit
                    );

                    $newPositions[$source->id] = $result['position'];
                    $newEntries = $result['entries'];

                    if (!empty($newEntries)) {
                        $message = sprintf('Found %d new entries in %s', count($newEntries), $source->name);
                        $output->writeln($message);

                        $event = new LogWatcherBeforeNotifyEvent($source, $newEntries);
                        $this->eventDispatcher->dispatch($event);

                        if ($event->isPropagationStopped()) {
                            continue;
                        }

                        $this->logNotifier->notify($source->name, $event->entries);
                    }
                } catch (Exception $e) {
                    $message = sprintf('LogWatcherCommand Error reading %s: %s', $source->name, $e->getMessage());
                    $output->writeln($message);

                    continue;
                }
            }

            $this->logViewer->saveWatcherPositions($newPositions, $this->userIdentifier);

            if ($limitTime !== null && (time() - $startTime) >= $limitTime) {
                break;
            }

            sleep($interval);
        }

        $output->writeln('Finished log watching.');

        return Command::SUCCESS;
    }
}
