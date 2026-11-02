<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function array_pop;
use function explode;
use function implode;
use function preg_match;
use function rawurldecode;
use function rawurlencode;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function strpbrk;
use function str_replace;
use function parse_url;
use function strcasecmp;
use function strtolower;
use function substr;

final readonly class AssetUrlRewriter
{
    public function __construct(
        private AssetFingerprintManifest $manifest,
        private HtmlUrlRewriter $htmlUrlRewriter = new HtmlUrlRewriter(),
    ) {}

    public function rewrite(string $html, string $rootPath = ''): string
    {
        if (
            $html === ''
            || $this->manifest->isEmpty()
            || (
                !$this->manifest->containsLogicalPath($html)
                && !str_contains($html, '%')
                && !str_contains($html, '/.')
            )
            || !str_contains($html, '=')
        ) {
            return $html;
        }

        if (str_contains($rootPath, '://') && !str_ends_with($rootPath, '/')) {
            $rootPath .= '/';
        }

        return $this->htmlUrlRewriter->rewrite(
            $html,
            fn(string $url): string => $this->rewriteUrl($url, $rootPath),
        );
    }

    private function rewriteUrl(string $url, string $rootPath): string
    {
        // Already-relative template assets need only prefix stripping and manifest lookup.
        if (
            str_starts_with($url, '.')
            && str_starts_with($url, $rootPath)
            && strpbrk($url, ':?#%') === false
        ) {
            return $rootPath . $this->manifest->resolve(substr($url, strlen($rootPath)));
        }

        if (
            $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, 'data:')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'javascript:')
            || str_starts_with($url, '//')
        ) {
            return $url;
        }

        [$path, $suffix] = $this->splitSuffix($url);
        $extracted = $this->extractLogicalPath($path, $rootPath);
        if ($extracted === null) {
            return $url;
        }
        [$prefix, $logicalPath] = $extracted;
        $resolvedPath = $this->manifest->resolve($logicalPath);

        // Decode only path data, once. Encoded separators must not select a different asset directory.
        if ($resolvedPath === $logicalPath && str_contains($logicalPath, '%')) {
            if (preg_match('~%(?:2f|5c)~i', $logicalPath) === 1) {
                return $url;
            }
            $decodedPath = rawurldecode($logicalPath);
            $resolvedPath = $this->manifest->resolve($decodedPath);
            if ($resolvedPath === $decodedPath) {
                return $url;
            }
            $resolvedPath = str_replace('%2F', '/', rawurlencode($resolvedPath));
        }

        if ($resolvedPath === $logicalPath) {
            return $url;
        }

        if ($prefix === '' && $rootPath !== '' && $rootPath !== '/') {
            $prefix = $rootPath;
        }

        return $prefix . $resolvedPath . $suffix;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSuffix(string $url): array
    {
        $queryPosition = strpos($url, '?');
        $fragmentPosition = strpos($url, '#');

        if ($queryPosition === false && $fragmentPosition === false) {
            return [$url, ''];
        }

        $cutPosition = match (true) {
            $queryPosition === false => $fragmentPosition,
            $fragmentPosition === false => $queryPosition,
            default => min($queryPosition, $fragmentPosition),
        };

        return [substr($url, 0, $cutPosition), substr($url, $cutPosition)];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function extractLogicalPath(string $path, string $rootPath): ?array
    {
        if (str_contains($path, '://')) {
            return $this->extractAbsolutePath($path, $rootPath);
        }

        if (str_starts_with($path, '/')) {
            return ['', AssetFingerprintManifest::normalizePath($path)];
        }

        if ($rootPath !== '' && $rootPath !== '/' && str_starts_with($path, $rootPath)) {
            return [$rootPath, substr($path, strlen($rootPath))];
        }

        return ['', $path];
    }

    /** @return array{0: string, 1: string}|null */
    private function extractAbsolutePath(string $path, string $rootPath): ?array
    {
        $root = parse_url($rootPath);
        $url = parse_url($path);
        if ($root === false || $url === false || !isset($root['scheme'], $root['host'], $url['scheme'], $url['host'])) {
            return null;
        }

        $scheme = strtolower($root['scheme']);
        $defaultPort = match ($scheme) {
            'https' => 443,
            'http' => 80,
            default => null,
        };
        if (
            $scheme !== strtolower($url['scheme'])
            || strcasecmp($root['host'], $url['host']) !== 0
            || ($root['port'] ?? $defaultPort) !== ($url['port'] ?? $defaultPort)
        ) {
            return null;
        }

        $basePath = $root['path'] ?? '/';
        $urlPath = $url['path'] ?? '';
        if (str_contains($urlPath, '/.')) {
            $urlPath = $this->removeDotSegments($urlPath);
        }
        if (!str_starts_with($urlPath, $basePath)) {
            return null;
        }

        return [$rootPath, substr($urlPath, strlen($basePath))];
    }

    private function removeDotSegments(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                // The first empty segment is the absolute path's leading slash.
                if (count($segments) > 1) {
                    array_pop($segments);
                }
                continue;
            }
            $segments[] = $segment;
        }
        if (str_ends_with($path, '/.') || str_ends_with($path, '/..')) {
            $segments[] = '';
        }

        return implode('/', $segments);
    }
}
