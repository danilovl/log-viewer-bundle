<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class GetStructureAction
{
    public function __construct(private LogSourceManager $sourceManager) {}

    public function __invoke(): JsonResponse
    {
        $structure = $this->sourceManager->getAllData();

        return new JsonResponse($structure);
    }
}
