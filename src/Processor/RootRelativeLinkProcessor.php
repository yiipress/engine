<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Build\UrlResolver;
use YiiPress\Content\Model\Entry;

use function in_array;
use function preg_replace_callback;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * Rewrites root-relative `href` and `src` URLs in rendered content (`/about/`) relative to the current page,
 * the same way the `$url()` template helper does, so they keep working when `base_url` has a path.
 */
final class RootRelativeLinkProcessor implements ContentProcessorInterface, RootPathAwareProcessorInterface
{
    private const string TAG_PATTERN = '~<!--.*?(?:-->|$)(*SKIP)(*F)|'
        . '(<(?:(script|style|textarea|title)\b|[a-z][a-z0-9:-]*\b)'
        . '(?:[^"\'<>]|"[^"]*"|\'[^\']*\')*>)(?(2)(.*?(?:</\2\s*>|$)))~is';

    private const string ATTRIBUTE_PATTERN = '~(\s+)([a-z_:][a-z0-9_.:-]*)(\s*=\s*)'
        . '(?:(["\'])(.*?)\4|([^\s"\'`=<>]+))~is';

    public function __construct(
        private string $rootPath = './',
    ) {}

    public function applyRootPath(string $rootPath): void
    {
        $this->rootPath = $rootPath;
    }

    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, '/')) {
            return $content;
        }

        return preg_replace_callback(
            self::TAG_PATTERN,
            fn(array $matches): string => $this->rebaseTag($matches[1]) . ($matches[3] ?? ''),
            $content,
        ) ?? $content;
    }

    /**
     * Consumes whole attribute values so URL-like text inside another attribute stays unchanged.
     */
    private function rebaseTag(string $tag): string
    {
        return preg_replace_callback(
            self::ATTRIBUTE_PATTERN,
            function (array $matches): string {
                if (!in_array(strtolower($matches[2]), ['href', 'src'], true)) {
                    return $matches[0];
                }

                $quote = $matches[4] ?? '';
                $path = $matches[6] ?? $matches[5] ?? '';
                if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
                    return $matches[0];
                }

                return $matches[1] . $matches[2] . $matches[3]
                    . $quote . UrlResolver::sitePath($path, $this->rootPath) . $quote;
            },
            $tag,
        ) ?? $tag;
    }
}
