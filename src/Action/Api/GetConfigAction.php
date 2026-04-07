<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Action\Api;

use Danilovl\LogViewerBundle\DependencyInjection\Configuration;
use Danilovl\LogViewerBundle\Service\{
    RegexTemplateProvider,
    ConfigurationProvider
};
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class GetConfigAction
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        private RegexTemplateProvider $regexTemplateProvider
    ) {}

    public function __invoke(): JsonResponse
    {
        $aiChats = array_map(static function (array $chat): array {
            return [
                'name' => $chat['name'],
                'url' => $chat['url'],
                'hasPrompt' => $chat['has_prompt']
            ];
        }, $this->configurationProvider->aiChats);

        $data = [
            'dashboardPageStatisticEnabled' => $this->configurationProvider->dashboardPageStatisticEnabled,
            'logPageStatisticEnabled' => $this->configurationProvider->logPageStatisticEnabled,
            'sourceAllowDelete' => $this->configurationProvider->sourceAllowDelete,
            'sourceAllowDownload' => $this->configurationProvider->sourceAllowDownload,
            'apiPrefix' => $this->configurationProvider->apiPrefix,
            'sourceMaxFileSize' => $this->configurationProvider->sourceMaxFileSize,
            'parserGoEnabled' => $this->configurationProvider->parserGoEnabled,
            'cacheParserDetectEnabled' => $this->configurationProvider->cacheParserDetectEnabled,
            'cacheStatisticEnabled' => $this->configurationProvider->cacheStatisticEnabled,
            'cacheStatisticInterval' => $this->configurationProvider->cacheStatisticInterval,
            'logPageAutoRefreshEnabled' => $this->configurationProvider->logPageAutoRefreshEnabled,
            'logPageAutoRefreshInterval' => $this->configurationProvider->logPageAutoRefreshInterval,
            'logPageAutoRefreshShowCountdown' => $this->configurationProvider->logPageAutoRefreshShowCountdown,
            'logPageLimit' => $this->configurationProvider->logPageLimit,
            'dashboardPageAutoRefreshEnabled' => $this->configurationProvider->dashboardPageAutoRefreshEnabled,
            'dashboardPageAutoRefreshInterval' => $this->configurationProvider->dashboardPageAutoRefreshInterval,
            'dashboardPageAutoRefreshShowCountdown' => $this->configurationProvider->dashboardPageAutoRefreshShowCountdown,
            'liveLogPageEnabled' => $this->configurationProvider->liveLogPageEnabled,
            'liveLogPageInterval' => $this->configurationProvider->liveLogPageInterval,
            'liveSelectedLevels' => array_map('mb_strtolower', Configuration::LEVELS),
            'aiButtonLevels' => array_map('mb_strtoupper', $this->configurationProvider->aiButtonLevels),
            'aiChats' => $aiChats,
            'regexTemplates' => $this->regexTemplateProvider->getTemplates()
        ];

        return new JsonResponse($data);
    }
}
