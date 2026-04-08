<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Controller\Api;

use Danilovl\LogViewerBundle\Action\Api\{
    DeleteLogAction,
    DownloadLogAction,
    GetConfigAction,
    GetDashboardStatsAction,
    GetEntriesAction,
    GetEntriesCountAction,
    GetEntriesNewAction,
    GetFileContentAction,
    GetFoldersAction,
    GetStatsAction,
    GetStructureAction,
    GlobalSearchAction
};
use Danilovl\LogViewerBundle\DTO\LogViewerQuery;
use Symfony\Component\HttpFoundation\{
    JsonResponse,
    Request,
    Response
};
use Symfony\Component\HttpKernel\Attribute\{
    MapQueryParameter,
    MapQueryString
};
use Symfony\Component\Routing\Attribute\Route;

readonly class LogViewerController
{
    public function __construct(
        private GetConfigAction $getConfigAction,
        private GetStructureAction $getStructureAction,
        private GetFoldersAction $getFoldersAction,
        private GetEntriesAction $getEntriesAction,
        private GetEntriesNewAction $getEntriesNewAction,
        private GetEntriesCountAction $getEntriesCountAction,
        private GetStatsAction $getStatsAction,
        private GetDashboardStatsAction $getDashboardStatsAction,
        private DeleteLogAction $deleteLogAction,
        private DownloadLogAction $downloadLogAction,
        private GetFileContentAction $getFileContentAction,
        private GlobalSearchAction $globalSearchAction
    ) {}

    #[Route(
        path: '/config',
        name: 'danilovl_log_viewer_api_config',
        methods: [Request::METHOD_GET]
    )]
    public function config(): JsonResponse
    {
        return $this->getConfigAction->__invoke();
    }

    #[Route(
        path: '/structure',
        name: 'danilovl_log_viewer_api_structure',
        methods: [Request::METHOD_GET]
    )]
    public function structure(): JsonResponse
    {
        return $this->getStructureAction->__invoke();
    }

    #[Route(
        path: '/folders',
        name: 'danilovl_log_viewer_api_folders',
        methods: [Request::METHOD_GET]
    )]
    public function folders(): JsonResponse
    {
        return $this->getFoldersAction->__invoke();
    }

    #[Route(
        path: '/entries',
        name: 'danilovl_log_viewer_api_entries',
        methods: [Request::METHOD_GET]
    )]
    public function entries(
        #[MapQueryString]
        LogViewerQuery $query
    ): JsonResponse {
        return $this->getEntriesAction->__invoke($query);
    }

    #[Route(
        path: '/entries/new',
        name: 'danilovl_log_viewer_api_entries_new',
        methods: [Request::METHOD_GET]
    )]
    public function entriesNew(
        #[MapQueryParameter]
        ?string $levels = null,
        #[MapQueryParameter]
        ?string $sourceIds = null
    ): JsonResponse {
        return $this->getEntriesNewAction->__invoke($levels, $sourceIds);
    }

    #[Route(
        path: '/global-search',
        name: 'danilovl_log_viewer_api_global_search',
        methods: [Request::METHOD_GET]
    )]
    public function globalSearch(
        #[MapQueryString]
        LogViewerQuery $query
    ): JsonResponse {
        return $this->globalSearchAction->__invoke($query);
    }

    #[Route(
        path: '/entries-count',
        name: 'danilovl_log_viewer_api_entries_count',
        methods: [Request::METHOD_GET]
    )]
    public function entriesCount(
        #[MapQueryString]
        LogViewerQuery $query
    ): JsonResponse {
        return $this->getEntriesCountAction->__invoke($query);
    }

    #[Route(
        path: '/stats',
        name: 'danilovl_log_viewer_api_stats',
        methods: [Request::METHOD_GET]
    )]
    public function stats(
        #[MapQueryString]
        LogViewerQuery $query
    ): JsonResponse {
        return $this->getStatsAction->__invoke($query);
    }

    #[Route(
        path: '/dashboard-stats',
        name: 'danilovl_log_viewer_api_dashboard_stats',
        methods: [Request::METHOD_GET]
    )]
    public function dashboardStats(
        #[MapQueryParameter]
        string $timelineFormat = 'hour'
    ): JsonResponse {
        return $this->getDashboardStatsAction->__invoke($timelineFormat);
    }

    #[Route(
        path: '/delete',
        name: 'danilovl_log_viewer_api_delete',
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(
        #[MapQueryParameter]
        string $sourceId
    ): JsonResponse {
        return $this->deleteLogAction->__invoke($sourceId);
    }

    #[Route(
        path: '/download',
        name: 'danilovl_log_viewer_api_download',
        methods: [Request::METHOD_GET]
    )]
    public function download(
        #[MapQueryParameter]
        string $sourceId
    ): Response {
        return $this->downloadLogAction->__invoke($sourceId);
    }

    #[Route(
        path: '/file-content',
        name: 'danilovl_log_viewer_api_file_content',
        methods: [Request::METHOD_GET]
    )]
    public function fileContent(
        #[MapQueryParameter]
        string $sourceId,
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        int $limit = 100,
        #[MapQueryParameter]
        ?int $line = null
    ): JsonResponse {
        return $this->getFileContentAction->__invoke($sourceId, $page, $limit, $line);
    }
}
