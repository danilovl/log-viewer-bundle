<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\CompositeLogParser;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;

class GoLogArguments
{
    private const array KNOWN_PARSERS = [
        'monolog',
        'nginx_access',
        'nginx_error',
        'apache_access',
        'syslog',
        'auth',
        'kern',
        'php_fpm',
        'php_error',
        'supervisord',
        'mysql',
        'json'
    ];

    /** @var string[] */
    private array $arguments;

    public function __construct(
        string $binaryPath,
        string $filePath,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly CompositeLogParser $compositeLogParser
    ) {
        $this->arguments = [
            $binaryPath,
            '--file',
            $filePath
        ];
    }

    public function addMode(string $mode): self
    {
        $this->arguments[] = '--mode';
        $this->arguments[] = $mode;

        return $this;
    }

    public function addParser(?string $parserType): self
    {
        $parserName = $this->compositeLogParser->getParserName($parserType);
        if ($parserName !== null && in_array($parserName, self::KNOWN_PARSERS, true)) {
            $this->arguments[] = '--parser';
            $this->arguments[] = $parserName;
        }

        $pattern = $this->compositeLogParser->getPatternGo($parserType);
        if ($pattern !== null) {
            $this->arguments[] = '--pattern';
            $this->arguments[] = $pattern;
        }

        return $this;
    }

    public function addFilters(?LogViewerFilters $filters): self
    {
        if ($filters === null) {
            return $this;
        }

        if ($filters->level !== null) {
            $this->arguments[] = '--level';
            $this->arguments[] = $filters->level;
        }

        if (!empty($filters->levels)) {
            $this->arguments[] = '--levels';
            $this->arguments[] = implode(',', $filters->levels);
        }

        if ($filters->channel !== null) {
            $this->arguments[] = '--channel';
            $this->arguments[] = $filters->channel;
        }

        if ($filters->search !== null) {
            $this->arguments[] = '--search';
            $this->arguments[] = $filters->search;

            if ($filters->searchRegex) {
                $this->arguments[] = '--search-regex';
            }

            if ($filters->searchCaseSensitive) {
                $this->arguments[] = '--search-case-sensitive';
            }
        }

        if ($filters->dateFrom !== null) {
            $this->arguments[] = '--date-from';
            $this->arguments[] = $filters->dateFrom;
        }

        if ($filters->dateTo !== null) {
            $this->arguments[] = '--date-to';
            $this->arguments[] = $filters->dateTo;
        }

        return $this;
    }

    public function addPagination(
        int $limit,
        int $offset,
        ?string $cursor,
        string $sortDir
    ): self {
        $this->arguments[] = '--limit';
        $this->arguments[] = (string) $limit;

        $this->arguments[] = '--offset';
        $this->arguments[] = (string) $offset;

        $this->arguments[] = '--sort';
        $this->arguments[] = $sortDir;

        if ($cursor !== null) {
            $this->arguments[] = '--cursor';
            $this->arguments[] = $cursor;
        }

        return $this;
    }

    public function addHost(?string $hostName): self
    {
        if ($hostName === null) {
            return $this;
        }

        $hostConfig = $this->configurationProvider->findRemoteHost($hostName);
        if ($hostConfig === null) {
            return $this;
        }

        $this->arguments[] = '--host';
        $this->arguments[] = $hostConfig->host;
        $this->arguments[] = '--port';
        $this->arguments[] = (string) $hostConfig->port;
        $this->arguments[] = '--host-type';
        $this->arguments[] = $hostConfig->type;

        if ($hostConfig->user !== null) {
            $this->arguments[] = '--user';
            $this->arguments[] = $hostConfig->user;
        }

        if ($hostConfig->password !== null) {
            $this->arguments[] = '--password';
            $this->arguments[] = $hostConfig->password;
        }

        if ($hostConfig->sshKey !== null) {
            $this->arguments[] = '--ssh-key';
            $this->arguments[] = $hostConfig->sshKey;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return $this->arguments;
    }
}
