<?php

declare(strict_types=1);

namespace YiiPress\Build;

use JsonException;
use WeakMap;

use function bin2hex;
use function ctype_alpha;
use function max;
use function min;
use function preg_quote;
use function preg_split;
use function serialize;
use function strrpos;
use function strtoupper;
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
                !$this->containsAssetCandidate($html, $rootPath)
                && !$this->containsAbsoluteDotPath($html, $rootPath)
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

    private function containsAssetCandidate(string $html, string $rootPath): bool
    {
        // Absolute parsing can change control bytes. Preserve the original whole-page admission first.
        if (str_contains($rootPath, '://')) {
            return $this->manifest->containsLogicalPath($html) || str_contains($html, '%');
        }

        static $covers = null;
        /** @var WeakMap<AssetFingerprintManifest, array{signature: ?string, pattern: ?string}> $cache */
        $cache = $covers ??= new WeakMap();
        $cover = $cache[$this->manifest] ?? null;
        if ($cover !== null && $cover['signature'] === null) {
            return $this->manifest->containsLogicalPath($html) || str_contains($html, '%');
        }

        try {
            $signature = $this->manifest->signature();
        } catch (JsonException) {
            // Registration cannot remove an invalid UTF-8 key; this identity must keep the literal fallback.
            $cache[$this->manifest] = ['signature' => null, 'pattern' => null];

            return $this->manifest->containsLogicalPath($html) || str_contains($html, '%');
        }
        if ($cover === null || $cover['signature'] !== $signature) {
            $cover = ['signature' => $signature, 'pattern' => $this->buildTerminalCover()];
            $cache[$this->manifest] = $cover;
        }
        if ($cover['pattern'] !== null) {
            $matched = @preg_match($cover['pattern'], $html);
            if ($matched !== false) {
                return $matched === 1;
            }
            $cover['pattern'] = null;
            $cache[$this->manifest] = $cover;
        }

        return $this->manifest->containsLogicalPath($html) || str_contains($html, '%');
    }

    private function buildTerminalCover(): ?string
    {
        /** @var array<string, array{parts: array<int, string|null>, ranges: array<int, array{int, int}>}> $groups */
        $groups = [];
        $bytes = 0;
        $states = 0;
        foreach ($this->manifest->all() as $path => $_) {
            $path = (string) $path;
            $bytes += strlen($path);
            if ($bytes > 65536) {
                return null;
            }
            $slash = strrpos($path, '/');
            if ($slash === false || $slash === strlen($path) - 1) {
                return null;
            }
            $component = substr($path, $slash + 1);
            if (strlen($component) > 64) {
                return null;
            }
            /** @var list<string>|false $parts */
            $parts = preg_split('~([0-9]+)~', $component, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                return null;
            }
            $shape = $parts;
            $ranges = [];
            foreach ($parts as $i => $part) {
                if ($i % 2 === 1) {
                    $shape[$i] = null;
                    $ranges[$i] = [strlen($part), strlen($part)];
                }
            }
            $key = serialize($shape);
            if (!isset($groups[$key])) {
                $groups[$key] = ['parts' => $shape, 'ranges' => $ranges];
                $states += strlen($component) + 1;
            } else {
                foreach ($ranges as $i => [$minimum, $maximum]) {
                    $old = $groups[$key]['ranges'][$i];
                    $states += max($maximum - $old[1], 0);
                    $groups[$key]['ranges'][$i] = [min($minimum, $old[0]), max($maximum, $old[1])];
                }
            }
            if ($states > 4096) {
                return null;
            }
        }
        $branches = [];
        $patternBytes = 0;
        foreach ($groups as $group) {
            $branch = $this->terminalComponentPattern($group['parts'], $group['ranges']);
            $patternBytes += strlen($branch) + 1;
            if ($patternBytes > 8000) {
                return null;
            }
            $branches[] = $branch;
        }
        $pattern = '~(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=1000)/(?:' . implode('|', $branches)
            . ')(?=$|[?#\s"\'`=<>])~D';

        return strlen($pattern) <= 8192 ? $pattern : null;
    }

    /**
     * @param array<int, string|null> $parts
     * @param array<int, array{int, int}> $ranges
     */
    private function terminalComponentPattern(array $parts, array $ranges): string
    {
        $pattern = '';
        foreach ($parts as $i => $literal) {
            if ($literal === null) {
                [$minimum, $maximum] = $ranges[$i];
                $pattern .= '(?:[0-9]|%3[0-9]){' . $minimum . ',' . $maximum . '}';
                continue;
            }
            for ($j = 0, $length = strlen($literal); $j < $length; $j++) {
                $byte = $literal[$j];
                $hex = bin2hex($byte);
                $encoded = '%';
                for ($k = 0; $k < 2; $k++) {
                    $digit = $hex[$k];
                    $encoded .= ctype_alpha($digit) ? '[' . $digit . strtoupper($digit) . ']' : $digit;
                }
                $pattern .= '(?:' . preg_quote($byte, '~') . '|' . $encoded . ')';
            }
        }

        return $pattern;
    }

    private function containsAbsoluteDotPath(string $html, string $rootPath): bool
    {
        if (!str_contains($rootPath, '://')) {
            return false;
        }

        $root = parse_url($rootPath);

        return $root !== false && isset($root['scheme'], $root['host']) && str_contains($html, '/.');
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
