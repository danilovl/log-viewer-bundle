<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final readonly class LogViewerFilters
{
    /**
     * @param string[]|null $levels
     */
    public function __construct(
        public ?string $level,
        public ?array $levels,
        public ?string $channel,
        public ?string $search,
        public bool $searchRegex,
        public bool $searchCaseSensitive,
        public bool $hasFilters,
    ) {}

    /**
     * @param string[]|null $levels
     */
    public static function fromQueryParams(
        ?string $level,
        ?string $channel,
        ?string $search,
        bool $searchRegex = false,
        bool $searchCaseSensitive = false,
        ?array $levels = null
    ): self {
        $normalizedLevel = self::normalize($level, true);
        $normalizedChannel = self::normalize($channel);
        $normalizedSearch = self::normalize($search);

        $hasFilters = $normalizedLevel !== null || $normalizedChannel !== null || $normalizedSearch !== null || !empty($levels);

        return new self(
            $normalizedLevel,
            $levels,
            $normalizedChannel,
            $normalizedSearch,
            $searchRegex,
            $searchCaseSensitive,
            $hasFilters
        );
    }

    private static function normalize(?string $value, bool $upper = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $upper ? mb_strtoupper($value) : $value;
    }
}
