<?php

declare(strict_types=1);

namespace App\Build;

final class RelativePathHelper
{
    public static function rootPath(string $permalink): string
    {
        $trimmed = trim($permalink, '/');
        if ($trimmed === '') {
            return './';
        }

        $depth = substr_count($trimmed, '/') + 1;

        return str_repeat('../', $depth);
    }

    public static function relativize(string $targetPermalink, string $rootPath): string
    {
        return $rootPath . ltrim($targetPermalink, '/');
    }
}
