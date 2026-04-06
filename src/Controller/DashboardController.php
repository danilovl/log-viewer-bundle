<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Controller;

use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{
    Request,
    Response
};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/danilovl/log-viewer', name: 'danilovl_log_viewer_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider
    ) {}

    #[Route(
        path: '/{vueRouting}',
        name: 'dashboard',
        requirements: ['vueRouting' => '^(?!api|build).*'],
        defaults: ['vueRouting' => 'dashboard'],
        methods: [Request::METHOD_GET]
    )]
    public function dashboard(): Response
    {
        return $this->render('@LogViewer/dashboard/dashboard.html.twig', [
            'apiPrefix' => $this->configurationProvider->apiPrefix
        ]);
    }
}
