<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Extension\Install\InstallException;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function random_bytes;
use function rename;
use function sprintf;
use function strlen;
use function unlink;

/**
 * Replaces a file through a temporary file in the same directory and `rename()`, so readers never see half of it.
 */
final class AtomicFile
{
    public static function write(string $path, string $contents): void
    {
        StatePaths::makeDirectory(dirname($path));
        $temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temp, $contents) !== strlen($contents)) {
            @unlink($temp);
            throw new InstallException('filesystem_failed', sprintf('Could not write "%s".', $temp));
        }
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new InstallException('filesystem_failed', sprintf('Could not replace "%s".', $path));
        }
    }
}
