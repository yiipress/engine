<?php

declare(strict_types=1);

namespace App\Build;

use function preg_replace_callback;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

final readonly class AssetUrlRewriter
{
    /** @var list<string> */
    private array $logicalPaths;

    public function __construct(
        private AssetFingerprintManifest $manifest,
    ) {
        $this->logicalPaths = array_keys($manifest->all());
    }

    public function rewrite(string $html, string $rootPath = ''): string
    {
        if (
            $html === ''
            || $this->manifest->isEmpty()
            || !$this->containsLogicalAssetPath($html)
            || (!str_contains($html, 'href=') && !str_contains($html, 'src='))
        ) {
            return $html;
        }

        return preg_replace_callback(
            '/\b(href|src)=(["\'])([^"\']+)\2/i',
            fn (array $matches): string => $matches[1] . '=' . $matches[2] . $this->rewriteUrl($matches[3], $rootPath) . $matches[2],
            $html,
        ) ?? $html;
    }

    private function rewriteUrl(string $url, string $rootPath): string
    {
        if (
            $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, 'data:')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'javascript:')
            || str_contains($url, '://')
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

        return $prefix . $resolvedPath . $suffix;
    }

    private function containsLogicalAssetPath(string $html): bool
    {
        return array_any($this->logicalPaths, fn($logicalPath) => str_contains($html, $logicalPath));
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
            return ['/', AssetFingerprintManifest::normalizePath($path)];
        }

        if ($rootPath !== '' && $rootPath !== '/' && str_starts_with($path, $rootPath)) {
            return [$rootPath, substr($path, strlen($rootPath))];
        }

        return ['', $path];
    }
}
