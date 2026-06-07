<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\SiteConfig;

use function is_int;
use function is_string;
use function ltrim;
use function parse_url;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class UrlResolver
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

    public static function sitePath(string $path, string $rootPath): string
    {
        if (self::isExternalOrSpecialUrl($path)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if ($path === '') {
            return $rootPath;
        }

        if ($rootPath === '') {
            return $path;
        }

        if ($rootPath === '/') {
            return '/' . $path;
        }

        return rtrim($rootPath, '/') . '/' . $path;
    }

    public static function relativeUrl(string $url, string $rootPath): string
    {
        if (!self::isSiteRootPath($url)) {
            return $url;
        }

        return self::sitePath($url, $rootPath);
    }

    public static function relativeToPermalink(string $url, string $permalink): string
    {
        return self::relativeUrl($url, self::rootPath($permalink));
    }

    public static function browserUrl(SiteConfig $siteConfig, string $url): string
    {
        if (!self::isSiteRootPath($url)) {
            return $url;
        }

        $basePath = self::basePath($siteConfig);
        if ($basePath === '') {
            return $url;
        }

        return self::joinPaths('/' . $basePath . '/', $url);
    }

    public static function absoluteUrl(SiteConfig $siteConfig, string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (self::isAbsoluteUrl($url)) {
            return $url;
        }

        $baseOrigin = self::baseOrigin($siteConfig);
        if ($baseOrigin === '') {
            return self::browserUrl($siteConfig, $url);
        }

        if (self::isSiteRootPath($url)) {
            return $baseOrigin . self::browserUrl($siteConfig, $url);
        }

        $basePath = self::basePath($siteConfig);

        return $baseOrigin . self::joinPaths('/' . $basePath . '/', $url);
    }

    public static function isSamePublicUrl(SiteConfig $siteConfig, string $sourcePermalink, string $targetUrl): bool
    {
        $source = self::absoluteUrl($siteConfig, $sourcePermalink);
        if ($source === '') {
            $source = $sourcePermalink;
        }

        return self::normalizeForCompare($source) === self::normalizeForCompare(self::absoluteUrl($siteConfig, $targetUrl));
    }

    private static function isSiteRootPath(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//');
    }

    private static function isExternalOrSpecialUrl(string $url): bool
    {
        return $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, 'data:')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, 'javascript:')
            || self::isAbsoluteUrl($url);
    }

    private static function isAbsoluteUrl(string $url): bool
    {
        return str_contains($url, '://') || str_starts_with($url, '//');
    }

    private static function basePath(SiteConfig $siteConfig): string
    {
        if ($siteConfig->baseUrl === '') {
            return '';
        }

        return trim(self::parseStringComponent($siteConfig->baseUrl, PHP_URL_PATH), '/');
    }

    private static function baseOrigin(SiteConfig $siteConfig): string
    {
        if ($siteConfig->baseUrl === '') {
            return '';
        }

        $scheme = self::parseStringComponent($siteConfig->baseUrl, PHP_URL_SCHEME);
        $host = self::parseStringComponent($siteConfig->baseUrl, PHP_URL_HOST);
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = parse_url($siteConfig->baseUrl, PHP_URL_PORT);

        return $scheme . '://' . $host . (is_int($port) ? ':' . $port : '');
    }

    private static function parseStringComponent(string $url, int $component): string
    {
        $value = parse_url($url, $component);

        return is_string($value) ? $value : '';
    }

    private static function joinPaths(string $basePath, string $path): string
    {
        $joined = '/' . trim($basePath, '/') . '/' . ltrim($path, '/');
        if (str_ends_with($path, '/') && !str_ends_with($joined, '/')) {
            $joined .= '/';
        }

        return $joined;
    }

    private static function normalizeForCompare(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $url = substr($url, 0, $fragmentPosition);
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return rtrim($url, '/') ?: '/';
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : '';
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if (str_ends_with($path, '/index.html')) {
            $path = substr($path, 0, -10);
        }
        $path = rtrim($path, '/') ?: '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . $host . $port . $path . $query;
    }
}
