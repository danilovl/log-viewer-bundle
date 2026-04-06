<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class NginxErrorParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<timestamp>\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2}) \[(?P<level>[^\]]+)\] (?P<pid>\d+)#(?P<tid>\d+): (?:\*(?P<cid>\d+) )?(?P<message>.*)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'nginx_error',
                message: $line,
                file: $filename,
                normalizedTimestamp: ''
            );
        }

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: mb_strtoupper($matches['level']),
            channel: 'nginx_error',
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: [
                'pid' => $matches['pid'],
                'tid' => $matches['tid'],
                'cid' => $matches['cid'] !== '' ? $matches['cid'] : null
            ]
        );
    }

    public function getName(): string
    {
        return 'nginx_error';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'nginx_error';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'nginx_error';
    }
}
