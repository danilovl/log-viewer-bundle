<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class SyslogParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<timestamp>\w{3}\s+\d+\s+\d{2}:\d{2}:\d{2}) (?P<host>\S+) (?P<service>[^\[:]+)(?:\[(?P<pid>\d+)\])?: (?P<message>.*)$~';

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
                normalizedTimestamp: ''
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
            channel: $matches['service'],
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: $context
        );
    }

    public function getName(): string
    {
        return 'syslog';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'syslog';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getDateFormat(): string
    {
        return 'M d H:i:s';
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'syslog';
    }
}
