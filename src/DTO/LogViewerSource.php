<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final class LogViewerSource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $path,
        public ?string $host,
        public ?string $parserType,
        public bool $isValid,
        public bool $isEmpty,
        public bool $isTooLarge,
        public bool $canDelete,
        public bool $canDownload,
        public int $size,
        public string $modified,
    ) {}
}
