<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Interfaces;

use Danilovl\LogViewerBundle\DTO\LogEntry;

interface LogInterfaceParser extends LogGoParserNameInterface
{
    public function parse(string $line, string $filename): LogEntry;

    public function getName(): string;

    public function supports(?string $parserType): bool;

    public function getPattern(): string;
}
