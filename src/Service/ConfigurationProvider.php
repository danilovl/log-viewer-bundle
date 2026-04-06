<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Service;

use Danilovl\LogViewerBundle\DTO\{
    RemoteHost,
    NotifierRule
};

final readonly class ConfigurationProvider
{
    /**
     * @param string[] $sourceDirs
     * @param string[] $sourceFiles
     * @param string[] $sourceIgnore
     * @param array<string, string> $parserOverrides
     * @param RemoteHost[] $sourceRemoteHosts
     * @param string[] $aiButtonLevels
     * @param array<int, array{name: string, levels: string[], contains: string[], channels: string[]}> $notifierRules
     */
    public function __construct(
        public array $sourceDirs,
        public array $sourceFiles,
        public array $sourceIgnore,
        public ?int $sourceMaxFileSize,
        public bool $sourceAllowDelete,
        public bool $sourceAllowDownload,
        public ?string $parserDefault,
        public array $parserOverrides,
        public bool $parserGoEnabled,
        public string $parserGoBinaryPath,
        public bool $cacheParserDetectEnabled,
        public bool $cacheStatisticEnabled,
        public int $cacheStatisticInterval,
        public bool $dashboardPageStatisticEnabled,
        public bool $dashboardPageAutoRefreshEnabled,
        public int $dashboardPageAutoRefreshInterval,
        public bool $dashboardPageAutoRefreshShowCountdown,
        public bool $liveLogPageEnabled,
        public int $liveLogPageInterval,
        public bool $logPageStatisticEnabled,
        public bool $logPageAutoRefreshEnabled,
        public int $logPageAutoRefreshInterval,
        public bool $logPageAutoRefreshShowCountdown,
        public int $logPageLimit,
        public array $aiButtonLevels,
        public string $apiPrefix,
        public ?string $encoreBuildName,
        public array $sourceRemoteHosts,
        public bool $notifierEnabled,
        public array $notifierRules,
    ) {}

    /**
     * @return NotifierRule[]
     */
    public function getNotifierRules(): array
    {
        return array_map(static fn (array $rule): NotifierRule => NotifierRule::fromArray($rule), $this->notifierRules);
    }

    public function findRemoteHost(string $name): ?RemoteHost
    {
        return array_find($this->sourceRemoteHosts, static fn (RemoteHost $hostConfig): bool => $hostConfig->name === $name);
    }
}
