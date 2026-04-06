<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DTO\{
    LogViewerSource,
    LogViewerFilters
};
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    LogSourceManager
};
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    RequestStack
};
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Exception;

readonly class GetEntriesNewAction
{
    public function __construct(
        private LogViewer $logViewer,
        private LogSourceManager $logSourceManager,
        private RequestStack $requestStack,
        private ?TokenStorageInterface $tokenStorage = null
    ) {}

    public function __invoke(?string $levels = null, ?string $sourceIds = null): JsonResponse
    {
        /** @var list<LogViewerSource> $sources */
        $sources = $this->logSourceManager->getAllSources();

        if ($sourceIds !== null) {
            $allowedIds = explode(',', $sourceIds);
            $sources = array_filter($sources, static fn (LogViewerSource $source): bool => in_array($source->id, $allowedIds, true));
        }

        $userIdentifier = $this->tokenStorage?->getToken()?->getUser()?->getUserIdentifier();
        if (empty($userIdentifier)) {
            $userIdentifier = $this->requestStack->getSession()->getId();
        }
        if (empty($userIdentifier)) {
            $userIdentifier = 'anonymous';
        }

        $lastPositions = $this->logViewer->loadWatcherPositions($userIdentifier);
        if (empty($lastPositions)) {
            $lastPositions = [];
            foreach ($sources as $source) {
                if (!$source->isValid || $source->isEmpty) {
                    continue;
                }
                $lastPositions[$source->id] = (int) $source->size;
            }
            $this->logViewer->saveWatcherPositions($lastPositions, $userIdentifier);

            return new JsonResponse([
                'entries' => [],
                'count' => 0,
                'calculatedAt' => date('Y-m-d H:i:s')
            ]);
        }

        $allEntries = [];
        $positions = $lastPositions;

        foreach ($sources as $source) {
            if (!$source->isValid || $source->isEmpty) {
                continue;
            }

            if ($levels !== null) {
                $currentLevels = explode(',', $levels);
                $currentLevels = array_map(static function (string $level): string {
                    return mb_strtoupper($level);
                }, $currentLevels);
            } else {
                $currentLevels = null;
            }

            $logViewerFilters = LogViewerFilters::fromQueryParams(
                level: null,
                channel: null,
                search: null,
                levels: $currentLevels
            );

            $lastPosition = $lastPositions[$source->id] ?? 0;
            if ($lastPosition === 0) {
                $positions[$source->id] = (int) $source->size;

                continue;
            }

            try {
                $result = $this->logViewer->getNewEntries(
                    filePath: $source->path,
                    parserType: $source->parserType,
                    lastPosition: $lastPosition,
                    filters: $logViewerFilters,
                    host: $source->host
                );

                $positions[$source->id] = $result['position'];

                foreach ($result['entries'] as $entry) {
                    $allEntries[] = $entry->withSourceId($source->id);
                }
            } catch (Exception) {
                continue;
            }
        }

        $this->logViewer->saveWatcherPositions($positions, $userIdentifier);

        usort($allEntries, static function ($a, $b): int {
            return $b->timestamp <=> $a->timestamp;
        });

        return new JsonResponse([
            'entries' => $allEntries,
            'count' => count($allEntries),
            'calculatedAt' => date('Y-m-d H:i:s')
        ]);
    }
}
