<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final class NotifierRule
{
    /**
     * @param string[] $levels
     * @param string[] $contains
     * @param string[] $channels
     */
    public function __construct(
        public string $name,
        public array $levels,
        public array $contains,
        public array $channels
    ) {}

    /**
     * @param array{name: string, levels: string[], contains: string[], channels: string[]} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            levels: $data['levels'],
            contains: $data['contains'],
            channels: $data['channels']
        );
    }
}
