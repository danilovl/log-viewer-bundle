<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\LogInterfaceParser;
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class JsonLogParser implements LogInterfaceParser
{
    public function parse(string $line, string $filename): LogEntry
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($line, true);
        if ($data === null) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'json',
                message: $line,
                file: $filename,
                normalizedTimestamp: ''
            );
        }

        $timestampValue = $data['timestamp'] ?? '';
        $timestamp = is_scalar($timestampValue) ? (string) $timestampValue : '';

        $levelValue = $data['level'] ?? 'INFO';
        $level = is_scalar($levelValue) ? (string) $levelValue : 'INFO';

        $channelValue = $data['channel'] ?? 'app';
        $channel = is_scalar($channelValue) ? (string) $channelValue : 'app';

        $messageValue = $data['message'] ?? '';
        $message = is_scalar($messageValue) ? (string) $messageValue : '';

        $contextValue = $data['context'] ?? [];
        $context = is_array($contextValue) ? $contextValue : [];

        return new LogEntry(
            timestamp: $timestamp,
            level: $level,
            channel: $channel,
            message: $message,
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($timestamp),
            context: $context
        );
    }

    public function getName(): string
    {
        return 'json';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'json';
    }

    public function getPattern(): string
    {
        return '~^\{.*\}$~';
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'json';
    }
}
