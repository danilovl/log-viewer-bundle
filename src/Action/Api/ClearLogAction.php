<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Event\LogViewerClearEvent;
use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Response
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class ClearLogAction
{
    public function __construct(
        private LogSourceManager $sourceManager,
        private ConfigurationProvider $configurationProvider,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function __invoke(string $sourceId): JsonResponse
    {
        if (!$this->configurationProvider->sourceAllowDelete) {
            return new JsonResponse(
                data: ['message' => 'clearNotAllowed'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $source = $this->sourceManager->getSourceById($sourceId);
        if (!$source) {
            return new JsonResponse(
                data: ['message' => 'sourceNotFound'],
                status: Response::HTTP_NOT_FOUND
            );
        }

        if ($source->host !== null) {
            return new JsonResponse(
                data: ['message' => 'remoteClearNotAllowed'],
                status: Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->sourceManager->isWithinAllowedDirs($source->path)) {
            return new JsonResponse(
                data: ['message' => 'outsideAllowedDirs'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        if (!FileActionHelper::canClear($source->path, $this->configurationProvider->sourceAllowDelete)) {
            return new JsonResponse(
                data: ['message' => 'clearNotAllowed'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $event = new LogViewerClearEvent($source);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(
                data: ['message' => 'clearPropagationStopped'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $success = $this->sourceManager->clearFile($source->path);
        if (!$success) {
            return new JsonResponse(
                data: ['message' => 'clearErrorPermissions'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse;
    }
}
