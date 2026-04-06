<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Event\LogViewerEntriesEvent;
use Danilovl\LogViewerBundle\DTO\{
    LogViewerQuery,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\SourceInfoResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class GetEntriesCountAction
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

        $filters = LogViewerFilters::fromQueryParams(
            level: $query->level,
            channel: $query->channel,
            search: $query->search,
            searchRegex: $query->searchRegex,
            searchCaseSensitive: $query->searchCaseSensitive
        );

        $totalCount = $this->logViewer->getCount(
            filePath: $sourceInfo->filePath,
            parserType: $sourceInfo->parserType,
            filters: $filters,
            host: $sourceInfo->host
        );

        $event = new LogViewerEntriesEvent($sourceInfo, []);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(
                data: ['message' => 'viewPropagationStopped'],
                status: JsonResponse::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse([
            'totalCount' => $totalCount
        ]);
    }
}
