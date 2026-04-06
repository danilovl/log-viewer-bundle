<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Util;

final class FileActionHelper
{
    public static function canDelete(string $path, bool $allowDelete): bool
    {
        return $allowDelete && is_file($path) && is_writable($path);
    }

    public static function canDownload(string $path, bool $allowDownload): bool
    {
        return $allowDownload && is_file($path) && is_readable($path);
    }
}
