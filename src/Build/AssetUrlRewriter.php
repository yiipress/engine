<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strncasecmp;
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
            || !$this->manifest->containsLogicalPath($html)
            || !str_contains($html, '=')
        ) {
            return $html;
        }

        return $this->htmlUrlRewriter->rewrite(
            $html,
            fn(string $url): string => $this->rewriteUrl($url, $rootPath),
        );
    }

    private function rewriteUrl(string $url, string $rootPath): string
    {
        if (
            $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, 'data:')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'javascript:')
            || (str_contains($url, '://')
                && !(str_contains($rootPath, '://') && $this->hasRootPrefix($url, $rootPath)))
            || str_starts_with($url, '//')
        ) {
            return $url;
        }

        [$path, $suffix] = $this->splitSuffix($url);
        [$prefix, $logicalPath] = $this->extractLogicalPath($path, $rootPath);
        $resolvedPath = $this->manifest->resolve($logicalPath);

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
     * @return array{0: string, 1: string}
     */
    private function extractLogicalPath(string $path, string $rootPath): array
    {
        if (str_starts_with($path, '/')) {
            return ['', AssetFingerprintManifest::normalizePath($path)];
        }

        if ($rootPath !== '' && $rootPath !== '/' && $this->hasRootPrefix($path, $rootPath)) {
            return [$rootPath, substr($path, strlen($rootPath))];
        }

        return ['', $path];
    }

    private function hasRootPrefix(string $url, string $rootPath): bool
    {
        $schemeEnd = strpos($rootPath, '://');
        if ($schemeEnd === false) {
            return str_starts_with($url, $rootPath);
        }

        $urlSchemeEnd = strpos($url, '://');
        if ($urlSchemeEnd === false) {
            return false;
        }

        $rootOriginEnd = strpos($rootPath, '/', $schemeEnd + 3);
        $urlOriginEnd = strpos($url, '/', $urlSchemeEnd + 3);
        $originLength = $rootOriginEnd === false ? strlen($rootPath) : $rootOriginEnd;
        $urlOriginLength = $urlOriginEnd === false ? strlen($url) : $urlOriginEnd;

        return $originLength === $urlOriginLength
            && strncasecmp($url, $rootPath, $originLength) === 0
            && str_starts_with(substr($url, $originLength), substr($rootPath, $originLength));
    }
}
