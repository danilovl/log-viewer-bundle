<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DTO\{
    LogViewerQuery,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Service\SourceInfoResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class GlobalSearchAction
{
    public function __construct(
        private LogViewer $logViewer,
        private SourceInfoResolver $sourceInfoResolver
    ) {}

    public function __invoke(LogViewerQuery $query): JsonResponse
    {
        if (empty($query->sourceId) || (empty($query->level) && empty($query->search))) {
            return new JsonResponse(['entries' => [], 'count' => 0]);
        }

        try {
            $sourceInfos = $this->sourceInfoResolver->resolveMultiple($query->sourceId);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                data: ['message' => $e->getMessage()],
                status: $e->getStatusCode()
            );
        }

        $allEntries = [];
        $filters = LogViewerFilters::fromQueryParams(
            level: $query->level,
            channel: $query->channel,
            search: $query->search,
            dateFrom: $query->dateFrom,
            dateTo: $query->dateTo,
            searchRegex: $query->searchRegex,
            searchCaseSensitive: $query->searchCaseSensitive
        );

        foreach ($sourceInfos as $sourceInfo) {
            if ($sourceInfo->isEmpty) {
                continue;
            }

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

            $hostId = $sourceInfo->host ?? 'local';
            $hash = sha1($hostId . $sourceInfo->filePath);
            $sourceId = mb_substr($hash, 0, 12);

            foreach ($entries as $entry) {
                $allEntries[] = $entry->withSourceId($sourceId);
            }
        }

        usort($allEntries, static function ($a, $b) use ($query) {
            $timeA = $a->normalizedTimestamp !== '' ? $a->normalizedTimestamp : $a->timestamp;
            $timeB = $b->normalizedTimestamp !== '' ? $b->normalizedTimestamp : $b->timestamp;

            return $query->sortDir === 'desc' ? $timeB <=> $timeA : $timeA <=> $timeB;
        });

        $pagedEntries = array_slice($allEntries, 0, $query->limit);

        return new JsonResponse([
            'entries' => $pagedEntries,
            'count' => count($pagedEntries),
        ]);
    }
}
