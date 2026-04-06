<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class GetFoldersAction
{
    public function __construct(private LogSourceManager $sourceManager) {}

    public function __invoke(): JsonResponse
    {
        $folders = $this->sourceManager->getFolders();
        $count = count($folders);

        return new JsonResponse([
            'folders' => $folders,
            'totalCount' => $count,
        ]);
    }
}
