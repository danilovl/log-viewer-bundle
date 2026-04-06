<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LogViewerExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly Packages $packages
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('danilovl_log_viewer_assets', $this->renderAssets(...), ['is_safe' => ['html']])
        ];
    }

    public function renderAssets(): string
    {
        $entrypointName = 'log_viewer';

        $bundlePublicPath = 'bundles/logviewer/build/';
        $entrypointsPath = $this->projectDir . '/public/' . $bundlePublicPath . 'entrypoints.json';

        if (!file_exists($entrypointsPath)) {
            return sprintf('<!-- LogViewerBundle: entrypoints.json not found at %s -->', $entrypointsPath);
        }

        $content = file_get_contents($entrypointsPath);
        if ($content === false) {
            return sprintf('<!-- LogViewerBundle: could not read %s -->', $entrypointsPath);
        }

        $entrypoints = json_decode($content, true);
        if (!is_array($entrypoints) || !isset($entrypoints['entrypoints']) || !is_array($entrypoints['entrypoints'])) {
            return sprintf('<!-- LogViewerBundle: entrypoints key not found or invalid in %s -->', $entrypointsPath);
        }

        /** @var array<string, mixed> $allEntrypoints */
        $allEntrypoints = $entrypoints['entrypoints'];
        if (!isset($allEntrypoints[$entrypointName]) || !is_array($allEntrypoints[$entrypointName])) {
            return sprintf('<!-- LogViewerBundle: entrypoint "%s" not found in %s -->', $entrypointName, $entrypointsPath);
        }

        /** @var array{js?: array<string>, css?: array<string>} $assets */
        $assets = $allEntrypoints[$entrypointName];
        $html = '';

        if (isset($assets['js'])) {
            foreach ($assets['js'] as $js) {
                $html .= sprintf('<script src="%s" defer></script>', $this->packages->getUrl($js));
            }
        }

        if (isset($assets['css'])) {
            foreach ($assets['css'] as $css) {
                $html .= sprintf('<link rel="stylesheet" href="%s">', $this->packages->getUrl($css));
            }
        }

        return $html;
    }
}
