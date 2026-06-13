<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

final class FileWriter
{
    public static function write(string $filePath, string $contents): void
    {
        if (@file_put_contents($filePath, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write file "%s".', $filePath));
        }
    }
}
