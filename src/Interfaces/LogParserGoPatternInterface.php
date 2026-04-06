<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Interfaces;

interface LogParserGoPatternInterface
{
    public function getGoPattern(?string $parserType): string;
}
