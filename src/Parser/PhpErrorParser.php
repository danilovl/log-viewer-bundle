<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class PhpErrorParser implements LogInterfaceParser
{
    private const string PATTERN = '~^\[(?P<timestamp>.*?)\] PHP (?P<level>.*?):  (?P<message>.*?) in (?P<file>.*?) on line (?P<line>\d+)$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'ERROR',
                channel: 'php',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $matches['level'],
            channel: 'php',
            message: $matches['message'],
            file: $matches['file'],
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: ['line' => $matches['line']]
        );
    }

    public function getName(): string
    {
        return 'php';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'php';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'php_error';
    }
}
