<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Service;

use SplFileObject;

readonly class FileContentReader
{
    /**
     * @return string[]
     */
    public function readLines(string $filePath, int $page, int $limit, ?int $line = null): array
    {
        $lines = [];
        $startLine = $line ?? ($page - 1) * $limit;

        $file = new SplFileObject($filePath, 'r');
        $file->seek($startLine);

        $count = 0;
        while (!$file->eof() && $count < $limit) {
            $currentLine = $file->current();
            if (is_string($currentLine)) {
                $lines[] = mb_rtrim($currentLine, "\r\n");
            }

            $file->next();
            $count++;
        }

        return $lines;
    }

    public function getTotalLines(string $filePath): int
    {
        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key();
    }
}
