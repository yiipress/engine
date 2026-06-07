<?php

declare(strict_types=1);

namespace YiiPress\Build;

final class RelativePathHelper
{
    public static function rootPath(string $permalink): string
    {
        return UrlResolver::rootPath($permalink);
    }

    public static function relativize(string $targetPermalink, string $rootPath): string
    {
        return UrlResolver::sitePath($targetPermalink, $rootPath);
    }
}
