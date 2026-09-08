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

final class DirectoryRemover
{
    public static function remove(string $directory): void
    {
        if (is_link($directory)) {
            unlink($directory);
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
                    unlink($path);
                }
            }
        } finally {
            closedir($handle);
        }

        rmdir($directory);
    }
}
