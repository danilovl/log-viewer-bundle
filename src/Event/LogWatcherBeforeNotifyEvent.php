<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Event;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    LogViewerSource
};
use Symfony\Contracts\EventDispatcher\Event;

final class LogWatcherBeforeNotifyEvent extends Event
{
    /**
     * @param list<LogEntry> $entries
     */
    public function __construct(
        public readonly LogViewerSource $source,
        public array $entries
    ) {}
}
