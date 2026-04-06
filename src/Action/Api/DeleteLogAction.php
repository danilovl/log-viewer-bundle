<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Event\LogViewerDeleteEvent;
use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Response
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class DeleteLogAction
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
                data: ['message' => 'deleteNotAllowed'],
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
                data: ['message' => 'remoteDeleteNotAllowed'],
                status: Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->sourceManager->isWithinAllowedDirs($source->path)) {
            return new JsonResponse(
                data: ['message' => 'outsideAllowedDirs'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        if (!FileActionHelper::canDelete($source->path, $this->configurationProvider->sourceAllowDelete)) {
            return new JsonResponse(
                data: ['message' => 'deleteNotAllowed'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $event = new LogViewerDeleteEvent($source);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(
                data: ['message' => 'deletePropagationStopped'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $success = $this->sourceManager->deleteFile($source->path);
        if (!$success) {
            return new JsonResponse(
                data: ['message' => 'deleteErrorPermissions'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse;
    }
}
