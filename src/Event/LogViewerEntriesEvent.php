<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Event;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    SourceInfo
};
use Symfony\Contracts\EventDispatcher\Event;

final class LogViewerEntriesEvent extends Event
{
    /** @param list<LogEntry> $entries */
    public function __construct(
        public readonly SourceInfo $sourceInfo,
        public array $entries
    ) {}
}
