<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Mock;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

abstract class KernelTest extends TestCase
{
    protected ?AppKernel $kernel = null;

    protected ContainerInterface $container;

    /**
     * @param array<string, mixed> $bundleConfig
     */
    protected function bootKernel(array $bundleConfig = []): void
    {
        $this->kernel = new AppKernel('test', true, $bundleConfig);
        $this->kernel->boot();

        /** @var ContainerInterface $container */
        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->container = $container;
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            $filesystem = new Filesystem;
            $filesystem->remove($this->kernel->getCacheDir());
            $filesystem->remove($this->kernel->getLogDir());

            $this->kernel->shutdown();
            $this->kernel = null;
        }

        parent::tearDown();
    }
}
