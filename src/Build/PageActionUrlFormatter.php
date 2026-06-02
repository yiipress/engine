<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

use function ltrim;
use function rawurlencode;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtr;
use function substr;

final class PageActionUrlFormatter
{
    public static function format(string $template, SiteConfig $siteConfig, Entry $entry, string $permalink, string $contentDir): string
    {
        $path = self::sourcePath($entry, $contentDir);
        $absoluteUrl = self::absoluteUrl($siteConfig, $permalink);

        return strtr($template, [
            '{path}' => self::encodePath($path),
            '{title}' => rawurlencode($entry->title),
            '{permalink}' => self::encodePath($permalink),
            '{url}' => rawurlencode($absoluteUrl),
        ]);
    }

    private static function sourcePath(Entry $entry, string $contentDir): string
    {
        $sourcePath = $entry->sourceFilePath();
        if ($contentDir !== '' && str_starts_with($sourcePath, rtrim($contentDir, '/') . '/')) {
            $sourcePath = substr($sourcePath, strlen(rtrim($contentDir, '/')) + 1);
        }

        return str_replace('\\', '/', $sourcePath);
    }

    private static function absoluteUrl(SiteConfig $siteConfig, string $permalink): string
    {
        if ($siteConfig->baseUrl === '') {
            return $permalink;
        }

        return rtrim($siteConfig->baseUrl, '/') . '/' . ltrim($permalink, '/');
    }

    private static function encodePath(string $path): string
    {
        return str_replace('%2F', '/', rawurlencode($path));
    }
}
