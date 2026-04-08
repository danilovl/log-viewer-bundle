<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DTO\{
    LogViewerQuery,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\{
    SourceInfoResolver
};
use Symfony\Component\HttpFoundation\{
    JsonResponse
};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Exception;

readonly class GetStatsAction
{
    public function __construct(
        private LogViewer $logViewer,
        private SourceInfoResolver $sourceInfoResolver
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

        try {
            $stats = $this->logViewer->getStats(
                filePath: $sourceInfo->filePath,
                parserType: $sourceInfo->parserType,
                filters: $filters,
                host: $sourceInfo->host
            );
        } catch (Exception) {
            return new JsonResponse(['stats' => null]);
        }

        return new JsonResponse([
            'stats' => $stats,
            'path' => $sourceInfo->filePath,
            'parserType' => $sourceInfo->parserType,
            'host' => $sourceInfo->host,
            'canDelete' => $sourceInfo->canDelete,
            'isDeletable' => $sourceInfo->isDeletable,
            'canDownload' => $sourceInfo->canDownload,
            'isDownloadable' => $sourceInfo->isDownloadable,
        ]);
    }
}
