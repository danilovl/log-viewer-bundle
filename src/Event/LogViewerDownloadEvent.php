<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Event;

use Danilovl\LogViewerBundle\DTO\LogViewerSource;
use Symfony\Contracts\EventDispatcher\Event;

final class LogViewerDownloadEvent extends Event
{
    public function __construct(public readonly LogViewerSource $source) {}
}
