<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final class LogViewerFolderStructure
{
    /** @var array<string, LogViewerFolderStructure> */
    public array $folders = [];

    /** @var list<LogViewerSource> */
    public array $files = [];

    public function __construct(
        public string $name,
        public string $path,
    ) {}

    /**
     * @return list<LogViewerSource>
     */
    public function getAllFiles(): array
    {
        $files = $this->files;

        foreach ($this->folders as $folder) {
            $files = array_merge($files, $folder->getAllFiles());
        }

        return $files;
    }
}
