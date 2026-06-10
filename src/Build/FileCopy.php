<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function clearstatcache;
use function copy;
use function filemtime;
use function filesize;
use function is_file;
use function sprintf;
use function touch;

final class FileCopy
{
    public static function copyIfChanged(string $sourcePath, string $targetPath): bool
    {
        if (self::destinationMatchesSource($sourcePath, $targetPath)) {
            return false;
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $sourcePath, $targetPath));
        }

        $mtime = filemtime($sourcePath);
        if ($mtime !== false) {
            touch($targetPath, $mtime);
        }

        return true;
    }

    public static function destinationMatchesSource(string $sourcePath, string $targetPath): bool
    {
        if (!is_file($targetPath)) {
            return false;
        }

        clearstatcache(true, $sourcePath);
        clearstatcache(true, $targetPath);

        return filesize($sourcePath) === filesize($targetPath)
            && filemtime($sourcePath) === filemtime($targetPath);
    }
}
