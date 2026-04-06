<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class MysqlParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<timestamp>.*?) (?P<thread_id>\d+) \[(?P<level>.*?)\] \[(?P<id>.*?)\] \[(?P<channel>.*?)\] (?P<message>.*)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'mysql',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        $levelOriginal = mb_strtoupper($matches['level']);
        $levelMap = [
            'NOTE' => 'INFO',
            'SYSTEM' => 'INFO',
            'WARNING' => 'WARNING',
            'ERROR' => 'ERROR'
        ];

        $level = $levelMap[$levelOriginal] ?? $levelOriginal;

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $level,
            channel: $matches['channel'],
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: [
                'thread_id' => $matches['thread_id'],
                'mysql_id' => $matches['id']
            ]
        );
    }

    public function getName(): string
    {
        return 'mysql';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'mysql';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'mysql';
    }
}
