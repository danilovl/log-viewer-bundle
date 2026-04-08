<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Service;

use Danilovl\LogViewerBundle\DTO\SourceInfo;
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Danilovl\LogViewerBundle\Parser\{
    CompositeLogParser,
    Reader\GoLogClient,
    Reader\LogSourceManager
};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class SourceInfoResolver
{
    public function __construct(
        private LogSourceManager $sourceManager,
        private CompositeLogParser $compositeLogParser,
        private ConfigurationProvider $configurationProvider,
        private GoLogClient $goClient
    ) {}

    /**
     * @return list<SourceInfo>
     */
    public function resolveMultiple(string $sourceIds): array
    {
        $ids = explode(',', $sourceIds);
        $result = [];
        foreach ($ids as $id) {
            try {
                $sourceInfo = $this->resolve($id, null);
                if (!$sourceInfo->isEmpty) {
                    $result[] = $sourceInfo;
                }
            } catch (BadRequestHttpException) {
                // Skip invalid sources in multiple mode
            }
        }

        if (empty($result)) {
            throw new BadRequestHttpException('sourceRequired');
        }

        return $result;
    }

    public function resolve(?string $sourceId, ?string $file): SourceInfo
    {
        $filePath = (string) $file;
        $parserType = null;
        $host = null;
        $isTooLarge = false;

        if ($sourceId !== null) {
            $source = $this->sourceManager->getSourceById($sourceId);
            if ($source !== null) {
                $filePath = $source->path;
                $parserType = $source->parserType;
                $host = $source->host;
                $isTooLarge = $source->isTooLarge;
            }
        }

        if ($filePath !== '') {
            $size = 0;
            if ($host === null) {
                $size = (int) @filesize($filePath);
            }

            $isEmpty = false;
            if ($host === null) {
                $isEmpty = is_file($filePath) && $size === 0;
            }

            if ($parserType === null && !$isEmpty) {
                if ($this->configurationProvider->parserGoEnabled) {
                    $parserType = $this->goClient->identify($filePath, $host);
                }

                if ($parserType === null) {
                    $line = $this->sourceManager->getFirstLine($filePath);
                    if ($line !== null) {
                        $parserType = $this->compositeLogParser->identify($line);
                    }
                }
            }

            $maxFileSize = $this->configurationProvider->sourceMaxFileSize;
            if ($host !== null) {
                $hostConfig = $this->configurationProvider->findRemoteHost($host);
                if ($hostConfig?->maxFileSize !== null) {
                    $maxFileSize = $hostConfig->maxFileSize;
                }
            }

            if (!$isTooLarge) {
                $isTooLarge = $maxFileSize !== null && $size > $maxFileSize;
            }
        }

        if ($filePath === '') {
            throw new BadRequestHttpException('sourceRequired');
        }

        if ($isTooLarge) {
            throw new BadRequestHttpException('fileSizeExceeds');
        }

        if ($parserType === null && !$isEmpty && $host === null) {
            $error = 'couldNotDetect';
            if (!is_readable($filePath)) {
                $error = 'fileNotReadable';
            }

            throw new BadRequestHttpException($error);
        }

        return new SourceInfo(
            filePath: $filePath,
            parserType: $parserType,
            host: $host,
            isEmpty: $isEmpty,
            size: $size,
            canDelete: $this->configurationProvider->sourceAllowDelete,
            isDeletable: FileActionHelper::canDelete($filePath, $this->configurationProvider->sourceAllowDelete),
            canDownload: $this->configurationProvider->sourceAllowDownload,
            isDownloadable: FileActionHelper::canDownload($filePath, $this->configurationProvider->sourceAllowDownload),
        );
    }
}
