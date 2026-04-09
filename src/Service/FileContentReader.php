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
        $totalLines = $this->getTotalLines($filePath);

        $file = new SplFileObject($filePath, 'r');
        $file->seek($startLine);

        $count = 0;
        while ($startLine + $count < $totalLines && !$file->eof() && $count < $limit) {
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
        if (filesize($filePath) === 0) {
            return 0;
        }

        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $handle = fopen($filePath, 'r');
        fseek($handle, -1, SEEK_END);
        $lastChar = fread($handle, 1);
        fclose($handle);

        if ($lastChar === "\n") {
            return $totalLines;
        }

        return $totalLines + 1;
    }
}
