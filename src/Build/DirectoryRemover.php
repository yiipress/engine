<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function closedir;
use function is_dir;
use function is_link;
use function opendir;
use function readdir;
use function rmdir;
use function sprintf;
use function unlink;

/**
 * Removes previous build output without following directory symlinks.
 *
 * Kept instead of yiisoft/files FileHelper::removeDirectory() for this hot path:
 * a five-iteration benchmark removing 10,000 page directories measured 462 ms here
 * versus 573 ms with FileHelper (24% slower). Results depend on the filesystem;
 * rerun DirectoryRemoverBench when reconsidering this implementation.
 */
final class DirectoryRemover
{
    public static function remove(string $directory): void
    {
        if (is_link($directory)) {
            if (!@unlink($directory)) {
                throw new RuntimeException(sprintf('Unable to remove "%s".', $directory));
            }
            return;
        }
        if (is_dir($directory)) {
            self::removeTree($directory);
        }
    }

    private static function removeTree(string $directory): void
    {
        $handle = @opendir($directory);
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open directory "%s".', $directory));
        }

        try {
            while (($name = readdir($handle)) !== false) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $directory . '/' . $name;
                if (is_dir($path) && !is_link($path)) {
                    self::removeTree($path);
                } else {
                    if (!@unlink($path)) {
                        throw new RuntimeException(sprintf('Unable to remove "%s".', $path));
                    }
                }
            }
        } finally {
            closedir($handle);
        }

        if (!@rmdir($directory)) {
            throw new RuntimeException(sprintf('Unable to remove directory "%s".', $directory));
        }
    }
}
