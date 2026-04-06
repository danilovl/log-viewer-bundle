<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Event\LogViewerDownloadEvent;
use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Response,
    StreamedResponse
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class DownloadLogAction
{
    public function __construct(
        private LogSourceManager $sourceManager,
        private ConfigurationProvider $configurationProvider,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function __invoke(string $sourceId): Response
    {
        if (!$this->configurationProvider->sourceAllowDownload) {
            return new JsonResponse(
                data: ['message' => 'downloadNotAllowed'],
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
                data: ['message' => 'remoteDownloadNotAllowed'],
                status: Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->sourceManager->isWithinAllowedDirs($source->path)) {
            return new JsonResponse(
                data: ['message' => 'outsideAllowedDirs'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        if (!FileActionHelper::canDownload($source->path, $this->configurationProvider->sourceAllowDownload)) {
            return new JsonResponse(
                data: ['message' => 'downloadNotAllowed'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $event = new LogViewerDownloadEvent($source);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return new JsonResponse(
                data: ['message' => 'downloadPropagationStopped'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $filePath = $source->path;

        $fileSize = filesize($filePath);
        $fileName = basename($filePath);

        $response = new StreamedResponse(static function () use ($filePath): void {
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                return;
            }

            while (!feof($handle)) {
                echo fread($handle, 8_192);
                flush();
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
        $response->headers->set('Content-Transfer-Encoding', 'binary');

        if ($fileSize !== false) {
            $response->headers->set('Content-Length', (string) $fileSize);
        }

        return $response;
    }
}
