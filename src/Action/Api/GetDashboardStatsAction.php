<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DTO\LogViewerSource;
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Util\DateNormalizer;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Exception;

readonly class GetDashboardStatsAction
{
    public function __construct(private LogViewer $logViewer) {}

    public function __invoke(string $timelineFormat = 'hour'): JsonResponse
    {
        /** @var list<LogViewerSource> $sources */
        $sources = $this->logViewer->getSources();
        $totalStats = [
            'totalFiles' => count($sources),
            'totalEntries' => 0,
            'totalSize' => 0,
            'calculatedAt' => null,
            'levels' => [],
            'channels' => [],
            'timeline' => [],
            'sources' => [],
        ];

        $calculatedAtTimes = [];
        foreach ($sources as $source) {
            if (!$source->isValid) {
                $totalStats['sources'][] = [
                    'id' => $source->id,
                    'name' => $source->name,
                    'path' => $source->path,
                    'total' => 0,
                    'size' => $source->size,
                    'calculatedAt' => null,
                    'isValid' => false,
                    'isEmpty' => $source->isEmpty,
                    'isTooLarge' => $source->isTooLarge,
                    'canDelete' => $source->canDelete,
                    'isDeletable' => $source->isDeletable,
                    'canDownload' => $source->canDownload,
                    'isDownloadable' => $source->isDownloadable,
                ];

                continue;
            }

            try {
                $stats = $this->logViewer->getStats(
                    filePath: $source->path,
                    parserType: $source->parserType,
                    host: $source->host,
                    timelineFormat: $timelineFormat
                );

                $totalStats['totalEntries'] += $stats->total;
                $totalStats['totalSize'] += $stats->size;

                if ($stats->calculatedAt) {
                    $calculatedAtTimes[] = $stats->calculatedAt;
                }

                foreach ($stats->levels as $level => $levelCount) {
                    $totalStats['levels'][$level] = ($totalStats['levels'][$level] ?? 0) + $levelCount;
                }

                foreach ($stats->channels as $channel => $channelCount) {
                    $totalStats['channels'][$channel] = ($totalStats['channels'][$channel] ?? 0) + $channelCount;
                }

                foreach ($stats->timeline as $time => $timeCount) {
                    $key = DateNormalizer::getTimelineKey((string) $time, $timelineFormat);
                    $totalStats['timeline'][$key] = ($totalStats['timeline'][$key] ?? 0) + $timeCount;
                }

                $totalStats['sources'][] = [
                    'id' => $source->id,
                    'name' => $source->name,
                    'path' => $source->path,
                    'total' => $stats->total,
                    'size' => $stats->size,
                    'calculatedAt' => $stats->calculatedAt,
                    'isValid' => true,
                    'isEmpty' => $source->isEmpty,
                    'isTooLarge' => $source->isTooLarge,
                    'canDelete' => $source->canDelete,
                    'isDeletable' => $source->isDeletable,
                    'canDownload' => $source->canDownload,
                    'isDownloadable' => $source->isDownloadable,
                ];
            } catch (Exception) {
                $totalStats['sources'][] = [
                    'id' => $source->id,
                    'name' => $source->name,
                    'path' => $source->path,
                    'total' => 0,
                    'size' => $source->size,
                    'calculatedAt' => null,
                    'isValid' => false,
                    'isEmpty' => $source->isEmpty,
                    'isTooLarge' => $source->isTooLarge,
                    'canDelete' => $source->canDelete,
                    'isDeletable' => $source->isDeletable,
                    'canDownload' => $source->canDownload,
                    'isDownloadable' => $source->isDownloadable,
                ];
            }
        }

        if (!empty($calculatedAtTimes)) {
            $totalStats['calculatedAt'] = min($calculatedAtTimes);
        } else {
            $totalStats['calculatedAt'] = date('Y-m-d H:i:s');
        }

        if (count($totalStats['timeline']) <= 5_000) {
            $endRange = new DateTimeImmutable($totalStats['calculatedAt']);

            $totalStats['timeline'] = DateNormalizer::fillTimelineGaps(
                timeline: $totalStats['timeline'],
                format: $timelineFormat,
                endRange: $endRange
            );
        }

        return new JsonResponse($totalStats);
    }
}
