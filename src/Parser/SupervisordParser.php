<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class SupervisordParser implements LogInterfaceParser
{
    private const string PATTERN = '~^(?P<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2},\d{3}) (?P<level>\w+) (?P<message>.*)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'supervisord',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        $levelOriginal = mb_strtoupper($matches['level']);
        $levelMap = [
            'CRIT' => 'ERROR',
            'ERRO' => 'ERROR',
            'WARN' => 'WARNING',
            'INFO' => 'INFO',
            'DEBG' => 'DEBUG'
        ];

        $level = $levelMap[$levelOriginal] ?? $levelOriginal;

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $level,
            channel: 'supervisord',
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: []
        );
    }

    public function getName(): string
    {
        return 'supervisord';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'supervisord';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'supervisord';
    }
}
