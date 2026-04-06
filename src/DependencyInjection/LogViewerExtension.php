<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DependencyInjection;

use Danilovl\LogViewerBundle\Util\IntervalParser;
use Danilovl\LogViewerBundle\DTO\{
    RemoteHost
};
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    RemoteLogReader,
    LogSourceManager
};
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Reference
};
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class LogViewerExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration;
        $config = $this->processConfiguration($configuration, $configs);

        $fileLocator = new FileLocator(__DIR__ . '/../Resources/config');
        $loader = new YamlFileLoader($container, $fileLocator);
        $loader->load('services.yaml');

        $sources = $config['sources'];
        $dashboardPage = $config['dashboard_page'];
        $logPage = $config['log_page'];

        $apiPrefix = $config['api_prefix'];

        $container->setParameter('danilovl_log_viewer.api_prefix', $apiPrefix);
        $container->setParameter('danilovl_log_viewer.dashboard_page.statistic_enabled', $dashboardPage['statistic_enabled']);
        $container->setParameter('danilovl_log_viewer.log_page.statistic_enabled', $logPage['statistic_enabled']);
        $container->setParameter('danilovl_log_viewer.sources.max_file_size', $sources['max_file_size']);

        /** @var list<array{
         *     name: string,
         *     type: string,
         *     host: string,
         *     port: int,
         *     user?: string,
         *     password?: string,
         *     ssh_key?: string,
         *     max_file_size?: int,
         *     dirs: string[],
         *     files: string[],
         *     ignore: string[]
         * }> $remoteHostsConfig
         */
        $remoteHostsConfig = $sources['remote_hosts'];

        $remoteHosts = [];
        if (!empty($remoteHostsConfig)) {
            $remoteHosts = array_map(static fn (array $config): RemoteHost => RemoteHost::fromArray($config), $remoteHostsConfig);
        }

        if (empty($remoteHosts)) {
            $container->removeDefinition(RemoteLogReader::class);
        }

        $container
            ->registerForAutoconfiguration(LogInterfaceParser::class)
            ->addTag('danilovl.log_viewer.parser');

        $container
            ->register(ConfigurationProvider::class, ConfigurationProvider::class)
            ->setArguments($this->getConfigurationProviderArguments($config, $remoteHosts));

        $parsersArgument = new TaggedIteratorArgument('danilovl.log_viewer.parser');
        $compositeLogParserDefinition = $container->register(CompositeLogParser::class);
        $compositeLogParserDefinition->setArgument('$parsers', $parsersArgument);
        $compositeLogParserDefinition->setAutowired(true);
        $compositeLogParserDefinition->setAutoconfigured(true);

        $cacheId = 'danilovl.log_viewer.cache';
        if ($container->has(TagAwareCacheInterface::class) || $container->hasAlias(TagAwareCacheInterface::class)) {
            $container->setAlias($cacheId, TagAwareCacheInterface::class);
        } elseif ($container->has('cache.app.taggable')) {
            $container->setAlias($cacheId, 'cache.app.taggable');
        } else {
            $container
                ->register($cacheId, FilesystemTagAwareAdapter::class)
                ->setArguments(['danilovl_log_viewer', 0, '%kernel.cache_dir%/danilovl_log_viewer'])
                ->setPublic(false);
        }

        $container
            ->getDefinition(LogSourceManager::class)
            ->setArgument('$cache', new Reference($cacheId));

        $container
            ->getDefinition(LogViewer::class)
            ->setArgument('$cache', new Reference($cacheId));
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * @param array<string, mixed> $config
     * @param RemoteHost[] $remoteHosts
     * @return array<string, mixed>
     */
    private function getConfigurationProviderArguments(array $config, array $remoteHosts): array
    {
        /** @var array<string, mixed> $sources */
        $sources = $config['sources'];
        /** @var array<string, mixed> $parser */
        $parser = $config['parser'];
        /** @var array<string, mixed> $cache */
        $cache = $config['cache'];
        /** @var array<string, mixed> $dashboardPage */
        $dashboardPage = $config['dashboard_page'];
        /** @var array<string, mixed> $liveLogPage */
        $liveLogPage = $config['live_log_page'];

        /** @var array<string, mixed> $logPage */
        $logPage = $config['log_page'];
        /** @var array<string, mixed> $ai */
        $ai = $config['ai'];
        /** @var array{rules: array<int, array{name: string, levels: string[], contains: string[], channels: string[]}>, enabled: bool} $notifier */
        $notifier = $config['notifier'];

        $notifierRules = array_map(static fn (array $rule): array => [
            'name' => $rule['name'],
            'levels' => $rule['levels'],
            'contains' => $rule['contains'],
            'channels' => $rule['channels'],
        ], $notifier['rules']);

        /** @var string $cacheStatisticInterval */
        $cacheStatisticInterval = $cache['statistic_interval'];
        /** @var string $dashboardPageAutoRefreshInterval */
        $dashboardPageAutoRefreshInterval = $dashboardPage['auto_refresh_interval'];
        /** @var string $liveLogPageInterval */
        $liveLogPageInterval = $liveLogPage['interval'];
        /** @var string $logPageAutoRefreshInterval */
        $logPageAutoRefreshInterval = $logPage['auto_refresh_interval'];

        return [
            '$sourceDirs' => $sources['dirs'],
            '$sourceFiles' => $sources['files'],
            '$sourceIgnore' => $sources['ignore'],
            '$sourceMaxFileSize' => $sources['max_file_size'],
            '$sourceAllowDelete' => $sources['allow_delete'],
            '$sourceAllowDownload' => $sources['allow_download'],
            '$parserDefault' => $parser['default'],
            '$parserOverrides' => $parser['overrides'],
            '$parserGoEnabled' => $parser['go_enabled'],
            '$parserGoBinaryPath' => $parser['go_binary_path'],
            '$cacheParserDetectEnabled' => $cache['parser_detect_enabled'],
            '$cacheStatisticEnabled' => $cache['statistic_enabled'],
            '$cacheStatisticInterval' => IntervalParser::parse($cacheStatisticInterval),
            '$dashboardPageStatisticEnabled' => $dashboardPage['statistic_enabled'],
            '$dashboardPageAutoRefreshEnabled' => $dashboardPage['auto_refresh_enabled'],
            '$dashboardPageAutoRefreshInterval' => IntervalParser::parse($dashboardPageAutoRefreshInterval),
            '$dashboardPageAutoRefreshShowCountdown' => $dashboardPage['auto_refresh_show_countdown'],
            '$liveLogPageEnabled' => $liveLogPage['enabled'],
            '$liveLogPageInterval' => IntervalParser::parse($liveLogPageInterval),
            '$logPageStatisticEnabled' => $logPage['statistic_enabled'],
            '$logPageAutoRefreshEnabled' => $logPage['auto_refresh_enabled'],
            '$logPageAutoRefreshInterval' => IntervalParser::parse($logPageAutoRefreshInterval),
            '$logPageAutoRefreshShowCountdown' => $logPage['auto_refresh_show_countdown'],
            '$logPageLimit' => $logPage['limit'],
            '$aiButtonLevels' => $ai['button_levels'],
            '$aiChats' => $ai['chats'],
            '$apiPrefix' => $config['api_prefix'],
            '$encoreBuildName' => $config['encore_build_name'],
            '$sourceRemoteHosts' => $remoteHosts,
            '$notifierEnabled' => $notifier['enabled'],
            '$notifierRules' => $notifierRules
        ];
    }
}
