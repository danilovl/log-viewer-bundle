<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\DependencyInjection;

use Danilovl\LogViewerBundle\DependencyInjection\LogViewerExtension;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    RemoteLogReader,
    LogSourceManager
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Reference
};
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

class LogViewerExtensionTest extends TestCase
{
    private LogViewerExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new LogViewerExtension;
        $this->container = new ContainerBuilder;
    }

    public function testRemoteLogReaderIsRemovedWhenNoRemoteHosts(): void
    {
        $configs = [
            'danilovl_log_viewer' => [
                'sources' => [
                    'remote_hosts' => []
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);
        $hasDefinition = $this->container->hasDefinition(RemoteLogReader::class);

        $this->assertFalse($hasDefinition);
    }

    public function testRemoteLogReaderIsPresentWhenRemoteHostsConfigured(): void
    {
        $configs = [
            'danilovl_log_viewer' => [
                'sources' => [
                    'remote_hosts' => [
                        'test_host' => [
                            'name' => 'test_host',
                            'type' => 'http',
                            'host' => 'example.com'
                        ]
                    ]
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);
        $hasDefinition = $this->container->hasDefinition(RemoteLogReader::class);

        $this->assertTrue($hasDefinition);
    }

    public function testLoadWithRemoteHostsFailsIfSsh2Missing(): void
    {
        if (extension_loaded('ssh2')) {
            $this->markTestSkipped('The ssh2 extension is loaded, cannot test the failure.');
        }

        $configs = [
            'danilovl_log_viewer' => [
                'sources' => [
                    'remote_hosts' => [
                        'test_host' => [
                            'name' => 'test_host',
                            'type' => 'ssh',
                            'host' => 'example.com'
                        ]
                    ]
                ]
            ]
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("The 'ssh2' PHP extension is required for SSH/SFTP logs.");

        $this->extension->load($configs, $this->container);
    }

    public function testLoadWithRemoteHostsPassesIfSsh2IsMissingButTypeIsHttp(): void
    {
        if (extension_loaded('ssh2')) {
            $this->markTestSkipped('The ssh2 extension is loaded, cannot test the failure.');
        }

        $configs = [
            'danilovl_log_viewer' => [
                'sources' => [
                    'remote_hosts' => [
                        'test_host' => [
                            'name' => 'test_host',
                            'type' => 'http',
                            'host' => 'example.com'
                        ]
                    ]
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);
        $hasParameter = $this->container->hasParameter('danilovl_log_viewer.api_prefix');

        $this->assertTrue($hasParameter);
    }

    public function testLoadWithNewCacheParameters(): void
    {
        $configs = [
            'danilovl_log_viewer' => [
                'cache' => [
                    'parser_detect_enabled' => true,
                    'statistic_enabled' => false,
                    'statistic_interval' => '10 sec'
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);

        $definition = $this->container->getDefinition(ConfigurationProvider::class);
        $arguments = $definition->getArguments();

        $this->assertTrue($arguments['$cacheParserDetectEnabled']);
        $this->assertFalse($arguments['$cacheStatisticEnabled']);
        $this->assertSame(10, $arguments['$cacheStatisticInterval']);
    }

    public function testDefaultCacheIsRegisteredWhenMissing(): void
    {
        $this->extension->load([], $this->container);

        $hasCacheInterface = $this->container->has(TagAwareCacheInterface::class);
        $hasLogViewerCache = $this->container->has('danilovl.log_viewer.cache');

        $this->assertFalse($hasCacheInterface);
        $this->assertTrue($hasLogViewerCache);

        $definition = $this->container->getDefinition('danilovl.log_viewer.cache');
        $this->assertSame(FilesystemTagAwareAdapter::class, $definition->getClass());

        $this->assertServiceHasCacheReference(LogSourceManager::class);
        $this->assertServiceHasCacheReference(LogViewer::class);
    }

    public function testDefaultCacheIsNotOverwritten(): void
    {
        $this->container->register(TagAwareCacheInterface::class, 'MyCustomCache');

        $this->extension->load([], $this->container);

        $hasCacheInterface = $this->container->has(TagAwareCacheInterface::class);
        $hasAlias = $this->container->hasAlias('danilovl.log_viewer.cache');

        $this->assertTrue($hasCacheInterface);
        $this->assertTrue($hasAlias);
        $this->assertSame(TagAwareCacheInterface::class, (string) $this->container->getAlias('danilovl.log_viewer.cache'));

        $this->assertServiceHasCacheReference(LogSourceManager::class);
        $this->assertServiceHasCacheReference(LogViewer::class);
    }

    public function testCacheIsAlwaysRegistered(): void
    {
        $configs = [
            'danilovl_log_viewer' => [
                'cache' => [
                    'parser_detect_enabled' => false,
                    'statistic_enabled' => false
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);

        $hasCache = $this->container->has('danilovl.log_viewer.cache') || $this->container->hasAlias('danilovl.log_viewer.cache');

        $this->assertTrue($hasCache);
        $this->assertServiceHasCacheReference(LogSourceManager::class);
        $this->assertServiceHasCacheReference(LogViewer::class);
    }

    private function assertServiceHasCacheReference(string $serviceId): void
    {
        $hasDefinition = $this->container->hasDefinition($serviceId);
        $this->assertTrue($hasDefinition);

        $definition = $this->container->getDefinition($serviceId);
        $cacheArgument = $definition->getArgument('$cache');

        $this->assertInstanceOf(Reference::class, $cacheArgument);
        $this->assertSame('danilovl.log_viewer.cache', (string) $cacheArgument);
    }
}
