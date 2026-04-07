<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\{
    LogInterfaceParser,
    LogParserGoPatternInterface
};
use Danilovl\LogViewerBundle\Util\DateNormalizer;

final class DoctrineParser implements LogInterfaceParser, LogParserGoPatternInterface
{
    private const string PATTERN = '~^\[(?P<timestamp>.*?)\] (?P<channel>.*?)\.(?P<level>.*?): (?P<message>.*?) (?P<context>\{.*?\})$~';

    public function parse(string $line, string $filename): LogEntry
    {
        $match = preg_match(self::PATTERN, $line, $matches);
        if (!$match) {
            return new LogEntry(
                timestamp: '',
                level: 'INFO',
                channel: 'doctrine',
                message: $line,
                file: $filename,
                normalizedTimestamp: '',
                context: []
            );
        }

        /** @var array<string, mixed> $context */
        $context = (array) (json_decode($matches['context'], true) ?? []);
        $sql = $context['sql'] ?? null;
        $params = $context['params'] ?? null;

        return new LogEntry(
            timestamp: $matches['timestamp'],
            level: $matches['level'],
            channel: $matches['channel'],
            message: $matches['message'],
            file: $filename,
            normalizedTimestamp: DateNormalizer::normalize($matches['timestamp']),
            sql: is_scalar($sql) ? (string) $sql : '',
            parameters: is_array($params) ? $params : [],
            context: $context
        );
    }

    public function getName(): string
    {
        return 'doctrine';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'doctrine';
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
        return 'doctrine';
    }

    public function getGoPattern(?string $parserType): string
    {
        return mb_trim(self::PATTERN, '~');
    }
}
