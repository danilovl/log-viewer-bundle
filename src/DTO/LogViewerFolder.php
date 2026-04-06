<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final readonly class LogViewerFolder
{
    public function __construct(
        public string $path,
        public string $name,
    ) {}
}
