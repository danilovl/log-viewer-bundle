<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\{
    LogViewerFolder,
    LogViewerFolderStructure,
    LogViewerSource,
    RemoteHost
};
use Danilovl\LogViewerBundle\Event\LogViewerDataEvent;
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\{
    ItemInterface,
    TagAwareCacheInterface
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LogSourceManager
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CompositeLogParser $compositeLogParser,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TagAwareCacheInterface $cache
    ) {}

    /**
     * @return list<LogViewerFolderStructure>
     */
    public function getAllData(): array
    {
        $sources = $this->getSources(
            dirs: $this->configurationProvider->sourceDirs,
            files: $this->configurationProvider->sourceFiles,
            ignore: $this->configurationProvider->sourceIgnore,
            remoteHosts: $this->configurationProvider->sourceRemoteHosts
        );
        $structure = $this->getFolderStructure($sources);

        $event = new LogViewerDataEvent($structure);
        $this->eventDispatcher->dispatch($event);

        return $event->structure;
    }

    /**
     * @return list<LogViewerSource>
     */
    public function getAllSources(): array
    {
        $structure = $this->getAllData();

        $files = [];
        foreach ($structure as $rootNode) {
            $files = array_merge($files, $rootNode->getAllFiles());
        }

        usort($files, static fn (LogViewerSource $a, LogViewerSource $b): int => strcmp($a->name, $b->name));

        return $files;
    }

    /**
     * @param string[] $dirs
     * @param string[] $files
     * @param string[] $ignore
     * @param RemoteHost[] $remoteHosts
     * @return list<LogViewerSource>
     */
    private function getSources(array $dirs, array $files, array $ignore, array $remoteHosts): array
    {
        $totalFiles = $this->getTotalFilesCount();
        $sources = [];
        $fs = new Filesystem;

        foreach ($files as $path) {
            $isExists = $fs->exists($path);
            $isLogFile = str_ends_with(mb_strtolower($path), '.log');
            $isIgnored = $this->isIgnored($path, $ignore);

            if ($isExists && $isLogFile && !$isIgnored) {
                $sources[] = $this->createSource(
                    path: $path,
                    totalFiles: $totalFiles
                );
            }
        }

        foreach ($dirs as $dir) {
            $isExists = $fs->exists($dir);
            if (!$isExists) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($dir)->name('*.log')->sortByName();

            foreach ($finder as $file) {
                $path = $file->getPathname();
                if ($this->isIgnored($path, $ignore)) {
                    continue;
                }

                $sources[] = $this->createSource(
                    path: $path,
                    totalFiles: $totalFiles
                );
            }
        }

        foreach ($remoteHosts as $hostConfig) {
            foreach ($hostConfig->files as $path) {
                if ($this->isIgnored($path, $hostConfig->ignore)) {
                    continue;
                }

                $sources[] = $this->createSource(
                    path: $path,
                    host: $hostConfig->name,
                    totalFiles: $totalFiles
                );
            }
        }

        usort($sources, static fn (LogViewerSource $a, LogViewerSource $b): int => strcmp($a->name, $b->name));

        return $sources;
    }

    /**
     * @param LogViewerSource[]|null $sources
     * @return list<LogViewerFolderStructure>
     */
    public function getFolderStructure(?array $sources = null): array
    {
        if ($sources === null) {
            $sources = $this->getAllSources();
        }

        $structure = [];
        $fs = new Filesystem;
        $addedSourceIds = [];

        if (!empty($this->configurationProvider->sourceFiles)) {
            $filesNode = new LogViewerFolderStructure(
                name: 'Individual files',
                path: '',
            );

            foreach ($this->configurationProvider->sourceFiles as $path) {
                foreach ($sources as $source) {
                    if ($source->host === null && $source->path === $path) {
                        $filesNode->files[] = $source;
                        $addedSourceIds[] = $source->id;

                        break;
                    }
                }
            }

            if (!empty($filesNode->files)) {
                $structure[] = $filesNode;
            }
        }

        foreach ($this->configurationProvider->sourceDirs as $rootDir) {
            $rootNode = new LogViewerFolderStructure(
                name: basename($rootDir),
                path: $rootDir,
            );

            foreach ($sources as $source) {
                if ($source->host !== null ||
                    in_array($source->id, $addedSourceIds, true) ||
                    !str_starts_with($source->path, $rootDir)
                ) {
                    continue;
                }

                $relativePath = $fs->makePathRelative(dirname($source->path), $rootDir);
                $trimmedPath = mb_trim($relativePath, '/.');
                $parts = $trimmedPath === '' ? [] : explode('/', $trimmedPath);

                $current = $rootNode;
                foreach ($parts as $part) {
                    if (!isset($current->folders[$part])) {
                        $current->folders[$part] = new LogViewerFolderStructure(
                            name: $part,
                            path: '',
                        );
                    }
                    $current = $current->folders[$part];
                }

                $current->files[] = $source;
                $addedSourceIds[] = $source->id;
            }

            if (!empty($rootNode->files) || !empty($rootNode->folders)) {
                $structure[] = $rootNode;
            }
        }

        foreach ($this->configurationProvider->sourceRemoteHosts as $hostConfig) {
            $hostNode = new LogViewerFolderStructure(
                name: $hostConfig->name . ' (Remote)',
                path: '',
            );

            foreach ($sources as $source) {
                if ($source->host === $hostConfig->name && !in_array($source->id, $addedSourceIds, true)) {
                    $hostNode->files[] = $source;
                    $addedSourceIds[] = $source->id;
                }
            }

            if (!empty($hostNode->files)) {
                $structure[] = $hostNode;
            }
        }

        return $structure;
    }

    /**
     * @return list<LogViewerFolder>
     */
    public function getFolders(): array
    {
        $folders = [];
        foreach ($this->configurationProvider->sourceDirs as $dir) {
            $folders[] = new LogViewerFolder(
                path: $dir,
                name: basename($dir),
            );
        }

        return $folders;
    }

    public function getSourceById(string $id): ?LogViewerSource
    {
        $sources = $this->getAllSources();

        return array_find($sources, static fn (LogViewerSource $source): bool => $source->id === $id);
    }

    private function createSource(string $path, ?string $host = null, int $totalFiles = 0): LogViewerSource
    {
        $hostId = $host ?? 'local';
        $hash = sha1($hostId . $path);
        $id = mb_substr($hash, 0, 12);
        $name = basename($path);
        $maxFileSize = $this->configurationProvider->sourceMaxFileSize;

        $size = 0;
        $modified = date('c');
        $isTooLarge = false;
        $isEmpty = false;
        $parser = null;
        $isValid = true;
        $canDelete = false;
        $canDownload = false;

        if ($host !== null) {
            $hostConfig = $this->configurationProvider->findRemoteHost($host);
            if ($hostConfig?->maxFileSize !== null) {
                $maxFileSize = $hostConfig->maxFileSize;
            }

            $isTooLarge = $maxFileSize !== null && $size > $maxFileSize;
        } elseif (is_file($path) && is_readable($path)) {
            $parser = $this->configurationProvider->parserOverrides[$path] ?? $this->configurationProvider->parserDefault;
            if ($parser === null) {
                if ($this->configurationProvider->cacheParserDetectEnabled) {
                    $cacheKey = LogViewer::CACHE_TAG . '.parser.' . $totalFiles . '.' . sha1($path);
                    $parser = $this->cache->get($cacheKey, function (ItemInterface $item) use ($path) {
                        $item->tag(LogViewer::CACHE_TAG);
                        $firstLine = $this->getFirstLine($path);

                        return $firstLine !== null ? $this->compositeLogParser->identify($firstLine) : null;
                    });
                } else {
                    $line = $this->getFirstLine($path);
                    if ($line !== null) {
                        $parser = $this->compositeLogParser->identify($line);
                    }
                }
            }

            $mtime = filemtime($path);
            $modified = date('c', (int) $mtime);
            $size = (int) filesize($path);
            $isEmpty = $size === 0;
            $isTooLarge = $maxFileSize !== null && $size > $maxFileSize;
            $isValid = ($parser !== null || $isEmpty) && !$isTooLarge;

            $canDelete = FileActionHelper::canDelete($path, $this->configurationProvider->sourceAllowDelete);
            $canDownload = FileActionHelper::canDownload($path, $this->configurationProvider->sourceAllowDownload);
        } else {
            $isValid = false;
        }

        return new LogViewerSource(
            id: $id,
            name: $name,
            path: $path,
            host: $host,
            parserType: $parser,
            isValid: $isValid,
            isEmpty: $isEmpty,
            isTooLarge: $isTooLarge,
            canDelete: $canDelete,
            canDownload: $canDownload,
            size: $size,
            modified: $modified
        );
    }

    private function getTotalFilesCount(): int
    {
        $count = count($this->configurationProvider->sourceFiles);
        $fs = new Filesystem;

        foreach ($this->configurationProvider->sourceDirs as $dir) {
            if ($fs->exists($dir)) {
                $finder = new Finder;
                $finder->files()->in($dir)->name('*.log');
                $count += $finder->count();
            }
        }

        foreach ($this->configurationProvider->sourceRemoteHosts as $hostConfig) {
            $count += count($hostConfig->files);
        }

        return $count;
    }

    public function getFirstLine(string $path): ?string
    {
        if (!is_readable($path) || is_dir($path)) {
            return null;
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        $line = null;
        while (($currentLine = fgets($handle)) !== false) {
            $trimmedLine = mb_trim($currentLine);
            if ($trimmedLine !== '') {
                $line = $trimmedLine;

                break;
            }
        }

        fclose($handle);

        return $line;
    }

    /**
     * @param string[] $ignoreFiles
     */
    private function isIgnored(string $path, array $ignoreFiles): bool
    {
        if (empty($ignoreFiles)) {
            return false;
        }

        foreach ($ignoreFiles as $ignore) {
            if ($path === $ignore || basename($path) === $ignore) {
                return true;
            }
        }

        return false;
    }

    public function deleteFile(string $path): bool
    {
        $path = realpath($path);
        if ($path === false || !FileActionHelper::canDelete($path, $this->configurationProvider->sourceAllowDelete)) {
            return false;
        }

        if (!$this->isWithinAllowedDirs($path)) {
            return false;
        }

        $fs = new Filesystem;
        $fs->remove($path);

        return true;
    }

    public function isWithinAllowedDirs(string $path): bool
    {
        $path = realpath($path);
        if ($path === false) {
            return false;
        }

        foreach ($this->configurationProvider->sourceDirs as $dir) {
            $realDir = realpath($dir);
            if ($realDir !== false && str_starts_with($path, $realDir)) {
                return true;
            }
        }

        foreach ($this->configurationProvider->sourceFiles as $file) {
            $realFile = realpath($file);
            if ($realFile !== false && $path === $realFile) {
                return true;
            }
        }

        return false;
    }
}
