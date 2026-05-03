<?php

declare(strict_types=1);

namespace YiiPress;

use Phar;

use function class_exists;
use function hash;
use function rtrim;
use function str_starts_with;
use function sys_get_temp_dir;

final class RuntimePaths
{
    public static function cachePath(string $projectRoot): string
    {
        return self::runtimePath($projectRoot) . '/cache';
    }

    public static function runtimePath(string $projectRoot, ?bool $packaged = null): string
    {
        $projectRoot = rtrim($projectRoot, '/');

        if (!($packaged ?? self::isPackaged())) {
            return $projectRoot . '/runtime';
        }

        return sys_get_temp_dir() . '/yiipress/runtime/' . hash('xxh128', $projectRoot);
    }

    private static function isPackaged(): bool
    {
        return str_starts_with(__FILE__, 'phar://') || class_exists(Phar::class, false) && Phar::running(false) !== '';
    }
}
