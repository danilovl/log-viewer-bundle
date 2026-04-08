<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final class LogEntry
{
    /**
     * @param array<string, mixed>|null $parameters
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public readonly string $timestamp,
        public string $level,
        public readonly string $channel,
        public readonly string $message,
        public readonly string $file,
        public readonly string $normalizedTimestamp,
        public readonly ?string $sql = null,
        public readonly ?array $parameters = null,
        public readonly ?array $context = null,
        public readonly ?string $sourceId = null,
        public readonly ?int $lineNumber = null,
    ) {
        $this->level = mb_strtoupper($level);
    }

    public function withSourceId(string $sourceId): self
    {
        return new self(
            timestamp: $this->timestamp,
            level: $this->level,
            channel: $this->channel,
            message: $this->message,
            file: $this->file,
            normalizedTimestamp: $this->normalizedTimestamp,
            sql: $this->sql,
            parameters: $this->parameters,
            context: $this->context,
            sourceId: $sourceId,
            lineNumber: $this->lineNumber
        );
    }

    public function withLineNumber(int $lineNumber): self
    {
        return new self(
            timestamp: $this->timestamp,
            level: $this->level,
            channel: $this->channel,
            message: $this->message,
            file: $this->file,
            normalizedTimestamp: $this->normalizedTimestamp,
            sql: $this->sql,
            parameters: $this->parameters,
            context: $this->context,
            sourceId: $this->sourceId,
            lineNumber: $lineNumber
        );
    }
}
