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
    ) {
        $this->level = mb_strtoupper($level);
    }

    public function withSourceId(string $sourceId): self
    {
        return new self(
            $this->timestamp,
            $this->level,
            $this->channel,
            $this->message,
            $this->file,
            $this->normalizedTimestamp,
            $this->sql,
            $this->parameters,
            $this->context,
            $sourceId
        );
    }
}
