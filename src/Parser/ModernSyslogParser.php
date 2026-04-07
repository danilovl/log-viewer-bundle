<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class ModernSyslogParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<timestamp>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}(?:[+-]\d{2}:\d{2}|Z)) (?P<host>\S+) (?P<channel>[^\[:]+)(?:\[(?P<pid>\d+)\])?: (?P<message>.*)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'syslog',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        $context = [
            'host' => $matches['host'],
        ];
        /** @phpstan-ignore-next-line */
        if (isset($matches['pid']) && $matches['pid'] !== '') {
            $context['pid'] = $matches['pid'];
        }

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: 'INFO',
            channel: $matches['channel'],
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: $context
        );
    }

    public function getName(): string
    {
        return 'syslog-modern';
    }

    public function supports(?string $parserType): bool
    {
        $types = ['auth', 'kern', 'php-fpm', 'syslog-modern'];

        return in_array($parserType, $types, true);
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d\TH:i:s.uP';
    }

    public function getGoParserName(?string $parserType): string
    {
        return match ($parserType) {
            'auth' => 'auth',
            'kern' => 'kern',
            'php-fpm' => 'php_fpm',
            default => 'syslog_modern',
        };
    }
}
