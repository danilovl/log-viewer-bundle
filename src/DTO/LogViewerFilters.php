<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

use Danilovl\LogViewerBundle\Util\DateNormalizer;

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
        public ?string $dateFrom,
        public ?string $dateTo,
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
        ?string $dateFrom = null,
        ?string $dateTo = null,
        bool $searchRegex = false,
        bool $searchCaseSensitive = false,
        ?array $levels = null
    ): self {
        $normalizedLevel = self::normalize($level, true);
        $normalizedChannel = self::normalize($channel);
        $normalizedSearch = self::normalize($search);
        $normalizedDateFrom = DateNormalizer::normalize((string) $dateFrom);
        $normalizedDateTo = DateNormalizer::normalize((string) $dateTo);

        $normalizedDateFrom = $normalizedDateFrom !== '' ? $normalizedDateFrom : null;
        $normalizedDateTo = $normalizedDateTo !== '' ? $normalizedDateTo : null;

        $hasFilters = $normalizedLevel !== null ||
            $normalizedChannel !== null ||
            $normalizedSearch !== null ||
            $normalizedDateFrom !== null ||
            $normalizedDateTo !== null ||
            !empty($levels);

        return new self(
            $normalizedLevel,
            $levels,
            $normalizedChannel,
            $normalizedSearch,
            $normalizedDateFrom,
            $normalizedDateTo,
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
