<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests;

trait LogPathTrait
{
    private function getLogPath(string $filename): string
    {
        return __DIR__ . '/Mock/Log/' . $filename;
    }

    private function getMockDir(): string
    {
        return __DIR__ . '/Mock/';
    }

    private function getRootDir(): string
    {
        return __DIR__ . '/..';
    }

    private function getGoBinaryPath(): string
    {
        return $this->getRootDir() . '/bin/dist/go-parser';
    }
}
