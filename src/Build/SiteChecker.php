<?php

declare(strict_types=1);

namespace YiiPress\Build;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_pop;
use function dirname;
use function explode;
use function file_get_contents;
use function get_headers;
use function html_entity_decode;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function parse_url;
use function preg_match;
use function preg_match_all;
use function rawurldecode;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function stream_context_create;
use function trim;

use const PHP_URL_PATH;

final readonly class SiteChecker
{
    /**
     * @param Closure(string): bool|null $externalChecker
     */
    public function __construct(
        private ?Closure $externalChecker = null,
    ) {}

    /**
     * @return list<SiteCheckIssue>
     */
    public function check(string $outputDir, bool $checkExternal = false): array
    {
        $htmlFiles = $this->htmlFiles($outputDir);
        $issues = [];

        foreach ($htmlFiles as $htmlFile) {
            $html = (string) file_get_contents($htmlFile);
            $anchors = $this->anchors($html);

            foreach ($this->links($html) as $target) {
                $issue = $this->checkTarget($outputDir, $htmlFile, $anchors, $target, $checkExternal);
                if ($issue !== null) {
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function htmlFiles(string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($outputDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isFile() && strtolower($item->getExtension()) === 'html') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function links(string $html): array
    {
        $lowerHtml = strtolower($html);
        if (!str_contains($lowerHtml, 'href=') && !str_contains($lowerHtml, 'src=')) {
            return [];
        }

        preg_match_all('/\b(?:href|src)\s*=\s*(["\'])(.*?)\1/i', $html, $matches, PREG_SET_ORDER);
        $links = [];
        foreach ($matches as $match) {
            $target = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
            if ($target !== '') {
                $links[] = $target;
            }
        }

        return $links;
    }

    /**
     * @return array<string, true>
     */
    private function anchors(string $html): array
    {
        $anchors = [];
        preg_match_all('/\b(?:id|name)\s*=\s*(["\'])(.*?)\1/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $anchor = html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
            if ($anchor !== '') {
                $anchors[$anchor] = true;
            }
        }

        return $anchors;
    }

    /**
     * @param array<string, true> $currentAnchors
     */
    private function checkTarget(string $outputDir, string $sourceFile, array $currentAnchors, string $target, bool $checkExternal): ?SiteCheckIssue
    {
        if ($this->shouldSkip($target)) {
            return null;
        }

        if ($this->isExternalHttpUrl($target)) {
            if (!$checkExternal || $this->externalIsReachable($target)) {
                return null;
            }

            return new SiteCheckIssue($sourceFile, $target, 'external link is not reachable');
        }

        [$path, $fragment] = $this->splitTarget($target);
        if ($path === '') {
            if ($fragment === '' || isset($currentAnchors[$fragment])) {
                return null;
            }

            return new SiteCheckIssue($sourceFile, $target, 'fragment not found');
        }

        $targetFile = $this->resolveTargetFile($outputDir, $sourceFile, $path);
        if ($targetFile === null || !is_file($targetFile)) {
            return new SiteCheckIssue($sourceFile, $target, 'local target not found');
        }

        if ($fragment === '') {
            return null;
        }

        $targetHtml = (string) file_get_contents($targetFile);
        if (isset($this->anchors($targetHtml)[$fragment])) {
            return null;
        }

        return new SiteCheckIssue($sourceFile, $target, 'fragment not found');
    }

    private function shouldSkip(string $target): bool
    {
        $lower = strtolower($target);

        return str_starts_with($lower, 'mailto:')
            || str_starts_with($lower, 'tel:')
            || str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'urn:');
    }

    private function isExternalHttpUrl(string $target): bool
    {
        $lower = strtolower($target);

        return str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || str_starts_with($lower, '//');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTarget(string $target): array
    {
        $path = $target;
        $fragment = '';

        $fragmentPosition = strpos($path, '#');
        if ($fragmentPosition !== false) {
            $fragment = rawurldecode(substr($path, $fragmentPosition + 1));
            $path = substr($path, 0, $fragmentPosition);
        }

        $queryPosition = strpos($path, '?');
        if ($queryPosition !== false) {
            $path = substr($path, 0, $queryPosition);
        }

        return [rawurldecode(trim($path)), $fragment];
    }

    private function resolveTargetFile(string $outputDir, string $sourceFile, string $path): ?string
    {
        $base = str_starts_with($path, '/')
            ? ''
            : substr(dirname($sourceFile), strlen($outputDir) + 1);
        $normalized = $this->normalizePath(($base !== '' ? $base . '/' : '') . $path);

        if ($normalized === null) {
            return null;
        }

        $targetPath = $outputDir . ($normalized !== '' ? '/' . $normalized : '');
        if (is_file($targetPath)) {
            return $targetPath;
        }
        if (is_dir($targetPath)) {
            return $targetPath . '/index.html';
        }
        if (str_ends_with($path, '/') || !str_contains($this->lastPathSegment($path), '.')) {
            return $targetPath . '/index.html';
        }

        return $targetPath;
    }

    private function normalizePath(string $path): ?string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : $path;
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    private function lastPathSegment(string $path): string
    {
        $path = trim($path, '/');
        $slashPosition = strrpos($path, '/');

        return $slashPosition === false ? $path : substr($path, $slashPosition + 1);
    }

    private function externalIsReachable(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if ($this->externalChecker !== null) {
            return ($this->externalChecker)($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $headers = @get_headers($url, true, $context);

        if ($this->headersAreSuccessful($headers)) {
            return true;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $headers = @get_headers($url, true, $context);

        return $this->headersAreSuccessful($headers);
    }

    private function headersAreSuccessful(mixed $headers): bool
    {
        if (!is_array($headers) || $headers === []) {
            return false;
        }

        $statusLine = $headers[0] ?? '';
        if (!is_string($statusLine) || preg_match('/\s(\d{3})\s?/', $statusLine, $matches) !== 1) {
            return false;
        }

        $status = (int) $matches[1];

        return $status >= 200 && $status < 400;
    }
}
