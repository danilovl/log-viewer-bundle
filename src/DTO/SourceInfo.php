<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final readonly class SourceInfo
{
    public function __construct(
        public string $filePath,
        public ?string $parserType,
        public ?string $host,
        public bool $isEmpty,
        public int $size,
        public bool $canDelete = false,
        public bool $isDeletable = false,
        public bool $canDownload = false,
        public bool $isDownloadable = false,
    ) {}
}
