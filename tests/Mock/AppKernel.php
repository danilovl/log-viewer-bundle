<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Mock;

use Danilovl\LogViewerBundle\LogViewerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    /**
     * @param array<string, mixed> $bundleConfig
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $bundleConfig = []
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield from [
            new FrameworkBundle,
            new LogViewerBundle,
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test_secret',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => false,
                'php_errors' => ['log' => false, 'throw' => false],
                'cache' => [
                    'app' => 'cache.adapter.filesystem',
                    'pools' => [
                        'danilovl.log_viewer.cache' => [
                            'adapter' => 'cache.adapter.filesystem'
                        ]
                    ]
                ]
            ]);

            $container->loadFromExtension('danilovl_log_viewer', $this->bundleConfig);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/LogViewerBundle/cache/' . uniqid();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/LogViewerBundle/logs/' . spl_object_hash($this);
    }
}
