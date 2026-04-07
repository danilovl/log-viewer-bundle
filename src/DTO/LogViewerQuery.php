<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

class LogViewerQuery
{
    public function __construct(
        public string $sourceId,
        public ?string $file = null,
        public ?string $level = null,
        public ?string $channel = null,
        public ?string $search = null,
        public bool $searchRegex = false,
        public bool $searchCaseSensitive = false,
        public int $limit = 50,
        public ?string $cursor = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $offset = 0,
        public string $sortDir = 'desc'
    ) {}
}
