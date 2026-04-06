<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final class LogViewerStats
{
    public int $total = 0;

    /** @var array<string, int> */
    public array $levels = [];

    /** @var array<string, int> */
    public array $channels = [];

    /** @var array<string, int> */
    public array $timeline = [];

    public function __construct(
        public int $size,
        public string $updatedAt,
        public string $calculatedAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $size = $data['size'] ?? 0;
        $updatedAt = $data['updatedAt'] ?? date('Y-m-d H:i:s');
        $calculatedAt = $data['calculatedAt'] ?? date('Y-m-d H:i:s');

        $stats = new self(
            size: is_numeric($size) ? (int) $size : 0,
            updatedAt: is_string($updatedAt) ? $updatedAt : date('Y-m-d H:i:s'),
            calculatedAt: is_string($calculatedAt) ? $calculatedAt : date('Y-m-d H:i:s'),
        );

        $total = $data['total'] ?? 0;
        $stats->total = is_numeric($total) ? (int) $total : 0;

        /** @var array<string, int> $levels */
        $levels = is_array($data['levels'] ?? null) ? $data['levels'] : [];
        $stats->levels = $levels;

        /** @var array<string, int> $channels */
        $channels = is_array($data['channels'] ?? null) ? $data['channels'] : [];
        $stats->channels = $channels;

        /** @var array<string, int> $timeline */
        $timeline = is_array($data['timeline'] ?? null) ? $data['timeline'] : [];
        $stats->timeline = $timeline;

        return $stats;
    }
}
