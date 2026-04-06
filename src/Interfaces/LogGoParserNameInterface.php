<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Interfaces;

interface LogGoParserNameInterface
{
    public function getGoParserName(?string $parserType): string;
}
