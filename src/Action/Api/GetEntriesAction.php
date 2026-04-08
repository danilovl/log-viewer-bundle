<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DTO\{
    LogViewerQuery,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Event\LogViewerEntriesEvent;
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\SourceInfoResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class GetEntriesAction
{
    public function __construct(
        private LogViewer $logViewer,
        private SourceInfoResolver $sourceInfoResolver,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function __invoke(LogViewerQuery $query): JsonResponse
    {
        try {
            $sourceInfo = $this->sourceInfoResolver->resolve($query->sourceId, null);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                data: ['message' => $e->getMessage()],
                status: $e->getStatusCode()
            );
        }

        if ($sourceInfo->isEmpty) {
            return new JsonResponse([
                'entries' => [],
                'count' => 0,
                'parserType' => $sourceInfo->parserType,
                'host' => $sourceInfo->host,
                'path' => $sourceInfo->filePath,
                'size' => $sourceInfo->size,
                'canDelete' => $sourceInfo->canDelete,
                'isDeletable' => $sourceInfo->isDeletable,
                'canDownload' => $sourceInfo->canDownload,
                'isDownloadable' => $sourceInfo->isDownloadable,
            ]);
        }

        $filters = LogViewerFilters::fromQueryParams(
            level: $query->level,
            channel: $query->channel,
            search: $query->search,
            dateFrom: $query->dateFrom,
            dateTo: $query->dateTo,
            searchRegex: $query->searchRegex,
            searchCaseSensitive: $query->searchCaseSensitive
        );

        $entries = $this->logViewer->getEntries(
            filePath: $sourceInfo->filePath,
            parserType: $sourceInfo->parserType,
            filters: $filters,
            limit: $query->limit,
            cursor: $query->cursor,
            sortDir: $query->sortDir,
            offset: $query->offset,
            host: $sourceInfo->host
        );

        $event = new LogViewerEntriesEvent($sourceInfo, $entries);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(
                data: ['message' => 'viewPropagationStopped'],
                status: JsonResponse::HTTP_FORBIDDEN
            );
        }

        $nextCursor = null;
        $count = count($event->entries);
        if ($count > 0) {
            $lastEntry = $event->entries[$count - 1];
            $nextCursor = $lastEntry->timestamp;
        }

        return new JsonResponse([
            'entries' => $event->entries,
            'count' => count($event->entries),
            'nextCursor' => $nextCursor,
            'parserType' => $sourceInfo->parserType,
            'host' => $sourceInfo->host,
            'path' => $sourceInfo->filePath,
            'size' => $sourceInfo->size,
            'canDelete' => $sourceInfo->canDelete,
            'isDeletable' => $sourceInfo->isDeletable,
            'canDownload' => $sourceInfo->canDownload,
            'isDownloadable' => $sourceInfo->isDownloadable,
        ]);
    }
}
