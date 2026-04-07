<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class ApacheAccessParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<remote_addr>.*?) - (?P<remote_user>.*?) \[(?P<timestamp>.*?)\] "(?P<request>.*?)" (?P<status>\d+) (?P<body_bytes_sent>.*)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'apache',
                message: $line,
                file: $filename,
                normalizedTimestamp: ''
            );
        }

        $statusCode = (int) $matches['status'];
        $level = $this->getLevelFromStatusCode($statusCode);

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $level,
            channel: 'apache',
            message: $matches['request'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: [
                'remote_addr' => $matches['remote_addr'],
                'remote_user' => $matches['remote_user'],
                'status' => $matches['status'],
                'body_bytes_sent' => $matches['body_bytes_sent']
            ]
        );
    }

    public function getName(): string
    {
        return 'apache';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'apache';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getDateFormat(): string
    {
        return 'd/M/Y:H:i:s O';
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'apache_access';
    }

    private function getLevelFromStatusCode(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'ERROR';
        }

        if ($statusCode >= 400) {
            return 'WARNING';
        }

        return 'INFO';
    }
}
