<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Event;

use Danilovl\LogViewerBundle\DTO\LogViewerFolderStructure;
use Symfony\Contracts\EventDispatcher\Event;

final class LogViewerDataEvent extends Event
{
    /**
     * @param list<LogViewerFolderStructure> $structure
     */
    public function __construct(public array $structure) {}
}
