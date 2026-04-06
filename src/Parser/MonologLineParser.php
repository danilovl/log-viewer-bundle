<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\{
    LogInterfaceParser,
    LogParserGoPatternInterface
};
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class MonologLineParser implements LogInterfaceParser, LogParserGoPatternInterface
{
    private const string PATTERN = '~^\[(?P<timestamp>[^\]]++)\] (?P<channel>[^.\s]++)\.(?P<level>[^:\s]++): (?P<message>.*?)(?: (?P<context>\{.*\}|\[.*\]))?(?: (?P<extra>\{.*\}|\[.*\]))?$~';

    public function parse(string $line, string $filename): LogEntry
    {
        if ($line !== '' && $line[0] === '[') {
            $posBracketEnd = mb_strpos($line, '] ');
            if ($posBracketEnd !== false) {
                $timestamp = mb_substr($line, 1, $posBracketEnd - 1);
                $afterTimestamp = mb_substr($line, $posBracketEnd + 2);

                $posDot = mb_strpos($afterTimestamp, '.');
                $posColon = mb_strpos($afterTimestamp, ': ');

                if ($posDot !== false && $posColon !== false && $posDot < $posColon) {
                    $channel = mb_substr($afterTimestamp, 0, $posDot);
                    $level = mb_substr($afterTimestamp, $posDot + 1, $posColon - $posDot - 1);
                    $messagePart = mb_substr($afterTimestamp, $posColon + 2);

                    if (str_contains($messagePart, '{') || str_contains($messagePart, '[')) {
                        return $this->parseWithRegex($line, $filename);
                    }

                    return new LogEntry(
                        timestamp: $timestamp,
                        level: $level,
                        channel: $channel,
                        message: $messagePart,
                        file: $filename,
                        normalizedTimestamp: DateNormalizer::normalize($timestamp),
                        context: []
                    );
                }
            }
        }

        return $this->parseWithRegex($line, $filename);
    }

    private function parseWithRegex(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'app',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        /** @var array<string, mixed> $context */
        $context = isset($matches['context']) ? (array) (json_decode($matches['context'], true) ?? []) : [];
        /** @var array<string, mixed> $extra */
        $extra = isset($matches['extra']) ? (array) (json_decode($matches['extra'], true) ?? []) : [];

        /** @var array<string, mixed> $fullContext */
        $fullContext = array_merge($context, $extra);

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $matches['level'],
            channel: $matches['channel'],
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            context: $fullContext
        );
    }

    public function getName(): string
    {
        return 'monolog';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'monolog';
    }

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'monolog';
    }

    public function getGoPattern(?string $parserType): string
    {
        return mb_trim(self::PATTERN, '~');
    }
}
