<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser;

use Danilovl\LogViewerBundle\DTO\LogEntry;
use Danilovl\LogViewerBundle\Interfaces\{
    LogInterfaceParser,
    LogParserCustomInterface,
    LogParserGoPatternInterface
};
use Throwable;
use Traversable;

class CompositeLogParser
{
    /** @var LogInterfaceParser[] */
    private array $parsers;

    /**
     * @param LogInterfaceParser[]|Traversable<LogInterfaceParser> $parsers
     */
    public function __construct(iterable $parsers)
    {
        $parsersArray = $parsers instanceof Traversable ? iterator_to_array($parsers) : (array) $parsers;
        $callback = self::sortParsers();

        usort($parsersArray, $callback);

        $this->parsers = $parsersArray;
    }

    public function parse(string $line, string $filename, ?string $parserType): LogEntry
    {
        if ($parserType !== null) {
            foreach ($this->parsers as $parser) {
                $isSupported = $parser->supports($parserType);
                if ($isSupported) {
                    return $parser->parse($line, $filename);
                }
            }
        }

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

    public function getPattern(?string $parserType): ?string
    {
        if ($parserType === null) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            $isSupported = $parser->supports($parserType);
            if ($isSupported) {
                return $parser->getPattern();
            }
        }

        return null;
    }

    public function getPatternGo(?string $parserType): ?string
    {
        if ($parserType === null) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            $isSupported = $parser->supports($parserType);
            if ($isSupported && $parser instanceof LogParserGoPatternInterface) {
                return $parser->getGoPattern($parserType);
            }
        }

        return null;
    }

    public function getParserName(?string $parserType): ?string
    {
        if ($parserType === null) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            $isSupported = $parser->supports($parserType);
            if ($isSupported) {
                return $parser->getGoParserName($parserType);
            }
        }

        return null;
    }

    public function getParserNameByGoName(string $goName): ?string
    {
        foreach ($this->parsers as $parser) {
            $parserName = $parser->getName();
            if ($parser->getGoParserName($parserName) === $goName) {
                return $parserName;
            }
        }

        return null;
    }

    public function getParser(?string $parserType): ?LogInterfaceParser
    {
        if ($parserType === null) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            $isSupported = $parser->supports($parserType);
            if ($isSupported) {
                return $parser;
            }
        }

        return null;
    }

    public function identify(string $line): ?string
    {
        $trimmedLine = mb_trim($line);
        if ($trimmedLine === '') {
            return null;
        }

        foreach ($this->parsers as $parser) {
            $pattern = $parser->getPattern();
            if ($pattern === '') {
                continue;
            }

            try {
                if (preg_match($pattern, $trimmedLine)) {
                    return $parser->getName();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public function isGoParserEnabled(LogInterfaceParser $parser): bool
    {
        if ($parser instanceof LogParserCustomInterface) {
            return $parser instanceof LogParserGoPatternInterface;
        }

        return true;
    }

    private static function sortParsers(): callable
    {
        return static function (LogInterfaceParser $a, LogInterfaceParser $b): int {
            if ($a instanceof LogParserCustomInterface || $b instanceof LogParserCustomInterface) {
                return $a instanceof LogParserCustomInterface ? -1 : 1;
            }

            if ($a instanceof MonologLineParser || $b instanceof MonologLineParser) {
                return $a instanceof MonologLineParser ? 1 : -1;
            }

            if ($a instanceof AccessLogParser || $b instanceof AccessLogParser) {
                return $a instanceof AccessLogParser ? 1 : -1;
            }

            if ($a instanceof ApacheAccessParser || $b instanceof ApacheAccessParser) {
                return $a instanceof ApacheAccessParser ? 1 : -1;
            }

            return 0;
        };
    }
}
