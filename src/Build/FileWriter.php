<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function basename;
use function dirname;
use function file_put_contents;
use function getmypid;
use function is_file;
use function rename;
use function sprintf;
use function strlen;
use function uniqid;
use function unlink;

final class FileWriter
{
    public static function write(string $path, string $contents, int $flags = 0): void
    {
        $bytes = @file_put_contents($path, $contents, $flags);
        if ($bytes === false || $bytes !== strlen($contents)) {
            throw new RuntimeException(sprintf('Unable to write file "%s".', $path));
        }
    }

    public static function writeAtomic(string $path, string $contents): void
    {
        $pid = getmypid();
        $tempPath = dirname($path) . '/.' . basename($path) . '.' . ($pid === false ? 0 : $pid) . '.' . uniqid('', true) . '.tmp';

        try {
            self::write($tempPath, $contents, LOCK_EX);

            if (!@rename($tempPath, $path)) {
                throw new RuntimeException(sprintf('Unable to replace file "%s".', $path));
            }
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
