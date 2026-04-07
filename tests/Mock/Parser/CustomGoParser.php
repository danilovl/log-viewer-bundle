<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Mock\Parser;

use Danilovl\LogViewerBundle\Util\DateNormalizer;
use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\{
    LogInterfaceParser,
    LogParserCustomInterface,
    LogParserGoPatternInterface
};

class CustomGoParser implements LogInterfaceParser, LogParserCustomInterface, LogParserGoPatternInterface
{
    public function parse(string $line, string $filename): LogEntry
    {
        $pattern = $this->getPattern();
        if (preg_match($pattern, $line, $matches)) {
            $timestamp = $matches['timestamp'] ?? '';
            $message = $matches['message'] ?? '';
            $normalizedTimestamp = DateNormalizer::normalize($timestamp);

            return new LogEntry(
                timestamp: $timestamp,
                level: 'INFO',
                channel: 'custom',
                message: $message,
                file: $filename,
                normalizedTimestamp: $normalizedTimestamp
            );
        }

        return new LogEntry(
            timestamp: '',
            level: '',
            channel: '',
            message: '',
            file: '',
            normalizedTimestamp: ''
        );
    }

    public function getName(): string
    {
        return 'custom_go';
    }

    public function getGoParserName(?string $parserType): string
    {
        return 'custom_go';
    }

    public function supports(?string $parserType): bool
    {
        return $parserType === 'custom_go';
    }

    public function getGoPattern(?string $parserType): string
    {
        return '(?P<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (?P<message>.*)';
    }

    public function getPattern(): string
    {
        return '/(?P<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (?P<message>.*)/';
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }
}
