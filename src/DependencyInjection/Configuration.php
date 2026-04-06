<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\{
    ArrayNodeDefinition,
    TreeBuilder
};
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'danilovl_log_viewer';

    public const array LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getSourcesNode('sources', 'Sources configuration.', ['%kernel.logs_dir%'], true))
                ->append($this->getParserNode())
                ->append($this->getCacheNode())
                ->append($this->getDashboardPageNode())
                ->append($this->getLiveLogPageNode())
                ->append($this->getLogPageNode())
                ->append($this->getAiNode())
                ->append($this->getNotifierNode())
                ->scalarNode('api_prefix')
                    ->info('Custom API prefix for bundle routes.')
                    ->defaultValue('/danilovl/log-viewer/api')
                ->end()
                ->scalarNode('encore_build_name')
                    ->info('Webpack Encore build name (leave null for default).')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function getParserNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('parser')->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('default')
                    ->info('Default parser for all files. If null, auto-detection will be used (usually "monolog" for Symfony logs).')
                    ->example('monolog')
                    ->defaultNull()
                ->end()
                ->arrayNode('overrides')
                    ->info('Parser overrides for specific files. Key is the absolute file path, value is the parser type.')
                    ->example(['/var/log/nginx/access.log' => 'nginx_access'])
                    ->useAttributeAsKey('path')
                    ->prototype('scalar')->end()
                ->end()
                ->booleanNode('go_enabled')
                    ->info('Enable Go-based parser for high performance.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('go_binary_path')
                    ->info('Path to the Go parser binary.')
                    ->defaultValue(dirname(__DIR__, 2) . '/bin/dist/go-parser')
                    ->validate()
                        ->always(static function ($v) {
                            return is_string($v) ? mb_trim($v) : $v;
                        })
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getCacheNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('cache')->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('parser_detect_enabled')
                    ->info('Enable caching for auto-detected parser types.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('statistic_enabled')
                    ->info('Enable caching for log statistics.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('statistic_interval')
                    ->info('Cache interval (e.g., "5 sec", "1 minute").')
                    ->defaultValue('5 sec')
                ->end()
            ->end();

        return $node;
    }

    private function getDashboardPageNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('dashboard_page')->getRootNode();

        $node
            ->info('Dashboard page settings.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('statistic_enabled')
                    ->info('Enable statistics for dashboard.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('auto_refresh_enabled')
                    ->info('Enable auto-refresh.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('auto_refresh_interval')
                    ->info('Auto-refresh interval (e.g., "5 sec", "1 minute").')
                    ->defaultValue('1 minute')
                ->end()
                ->booleanNode('auto_refresh_show_countdown')
                    ->info('Show countdown for auto-refresh.')
                    ->defaultFalse()
                ->end()
            ->end();

        return $node;
    }

    private function getLiveLogPageNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('live_log_page')->getRootNode();

        $node
            ->info('Live log page settings.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Enable live log page.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('interval')
                    ->info('Live update interval (e.g., "5 sec", "1 minute").')
                    ->defaultValue('5 sec')
                ->end()
            ->end();

        return $node;
    }

    private function getLogPageNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('log_page')->getRootNode();

        $node
            ->info('Log detail page settings.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('statistic_enabled')
                    ->info('Enable statistics for individual log files.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('auto_refresh_enabled')
                    ->info('Enable auto-refresh.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('auto_refresh_interval')
                    ->info('Auto-refresh interval (e.g., "5 sec", "1 minute").')
                    ->defaultValue('5 sec')
                ->end()
                ->booleanNode('auto_refresh_show_countdown')
                    ->info('Show countdown for auto-refresh.')
                    ->defaultFalse()
                ->end()
                ->integerNode('limit')
                    ->info('Entries limit.')
                    ->defaultValue(50)
                    ->min(1)
                ->end()
            ->end();

        return $node;
    }

    private function getAiNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('ai')->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->append($this->getLevelsNode('button_levels', 'Log levels for which "Ask AI" button appears on log entries.', self::LEVELS))
            ->end();

        return $node;
    }

    private function getNotifierNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('notifier')->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Enable notifications.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('rules')
                    ->info('Notification rules.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->info('Rule name.')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->append($this->getLevelsNode('levels', 'Log levels for which this rule applies.', self::LEVELS))
                            ->arrayNode('contains')
                                ->info('Keywords that the log entry must contain.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('channels')
                                ->info('Notifier channels to send the notification to.')
                                ->scalarPrototype()
                                    ->validate()
                                        ->ifNotInArray(['chat/slack', 'chat/telegram', 'email'])
                                        ->thenInvalid('Invalid notifier channel %s. Available channels: chat/slack, chat/telegram, email')
                                    ->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * @param string[] $levels
     */
    private function getLevelsNode(string $name, string $info, array $levels): ArrayNodeDefinition
    {
        $node = new TreeBuilder($name)->getRootNode();

        $node
            ->info($info)
            ->scalarPrototype()
                ->validate()
                    ->always(static function ($v) {
                        return is_string($v) ? mb_trim($v) : $v;
                    })
                ->end()
                ->validate()
                    ->ifNotInArray($levels)
                    ->thenInvalid('Invalid log level %s')
                ->end()
            ->end()
            ->defaultValue([]);

        return $node;
    }

    /**
     * @param string[] $dirsDefault
     */
    private function getSourcesNode(string $name, string $info, array $dirsDefault = [], bool $isRoot = false): ArrayNodeDefinition
    {
        $node = new TreeBuilder($name)->getRootNode();

        $node
            ->info($info)
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('dirs')
                    ->info('Directories to search for .log files.')
                    ->scalarPrototype()->end()
                    ->defaultValue($dirsDefault)
                ->end()
                ->arrayNode('files')
                    ->info('Individual log files.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('ignore')
                    ->info('Log files to ignore (supports filenames or full paths).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end();

        if ($isRoot) {
            $node
                ->children()
                    ->integerNode('max_file_size')
                        ->info('Max file size to be read in bytes. If null, read entire file.')
                        ->defaultNull()
                    ->end()
                    ->booleanNode('allow_delete')
                        ->info('Allow log file deletion from the dashboard.')
                        ->defaultFalse()
                    ->end()
                    ->booleanNode('allow_download')
                        ->info('Allow log file download from the dashboard.')
                        ->defaultFalse()
                    ->end()
                ->end();
        }

        $node
            ->children()
                ->append($this->getRemoteHostsNode())
            ->end();

        return $node;
    }

    private function getRemoteHostsNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('remote_hosts')->getRootNode();

        $node
            ->info('Remote hosts configuration.')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')
                        ->info('Unique name for this remote host.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('type')
                        ->info('Connection type (ssh, sftp, http).')
                        ->defaultValue('ssh')
                    ->end()
                    ->scalarNode('host')
                        ->info('Remote host address.')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->integerNode('port')
                        ->info('Remote host port.')
                        ->defaultValue(22)
                    ->end()
                    ->scalarNode('user')
                        ->info('Remote host user.')
                    ->end()
                    ->scalarNode('password')
                        ->info('Remote host password.')
                    ->end()
                    ->scalarNode('ssh_key')
                        ->info('Path to the SSH private key.')
                    ->end()
                    ->integerNode('max_file_size')
                        ->info('Max file size for this remote host.')
                        ->defaultNull()
                    ->end()
                    ->arrayNode('dirs')
                        ->info('Directories to search for .log files on the remote host.')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                    ->arrayNode('files')
                        ->info('Individual log files on the remote host.')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                    ->arrayNode('ignore')
                        ->info('Log files to ignore on the remote host.')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static function (array $v): bool {
                        return in_array($v['type'], ['ssh', 'sftp'], true) && !extension_loaded('ssh2');
                    })
                    ->thenInvalid("The 'ssh2' PHP extension is required for SSH/SFTP logs.")
                ->end()
            ->end();

        return $node;
    }
}
