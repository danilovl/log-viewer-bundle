<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Parser\Reader\{
    LogSourceManager,
    LogViewer
};
use Danilovl\LogViewerBundle\Util\FileActionHelper;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Response
};

readonly class GetFileContentAction
{
    public function __construct(
        private LogSourceManager $sourceManager,
        private LogViewer $logViewer
    ) {}

    public function __invoke(string $sourceId, int $page = 1, int $limit = 100, ?int $line = null): JsonResponse
    {
        $source = $this->sourceManager->getSourceById($sourceId);
        if (!$source) {
            return new JsonResponse(
                data: ['message' => 'sourceNotFound'],
                status: Response::HTTP_NOT_FOUND
            );
        }

        if ($source->host !== null && !$this->logViewer->isGoParserEnabled($source->parserType)) {
            return new JsonResponse(
                data: ['message' => 'remoteReadNotSupported'],
                status: Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->sourceManager->isWithinAllowedDirs($source->path)) {
            return new JsonResponse(
                data: ['message' => 'outsideAllowedDirs'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        if ($source->host === null && !FileActionHelper::canRead($source->path)) {
            return new JsonResponse(
                data: ['message' => 'readNotAllowed'],
                status: Response::HTTP_FORBIDDEN
            );
        }

        $data = $this->logViewer->getFileContent(
            filePath: $source->path,
            parserType: $source->parserType,
            page: $page,
            limit: $limit,
            line: $line,
            host: $source->host
        );

        return new JsonResponse($data);
    }
}
