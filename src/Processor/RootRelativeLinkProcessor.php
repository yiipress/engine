<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Build\UrlResolver;
use YiiPress\Content\Model\Entry;

use function preg_replace_callback;
use function str_contains;

/**
 * Rewrites root-relative `href` and `src` URLs in rendered content (`/about/`) relative to the current page,
 * the same way the `$url()` template helper does, so they keep working when `base_url` has a path.
 */
final class RootRelativeLinkProcessor implements ContentProcessorInterface, RootPathAwareProcessorInterface
{
    private const string PATTERN = '~(<[a-z][^>]*?\s(?:href|src)=)(["\'])/(?!/)([^"\']*)\2~i';

    public function __construct(
        private string $rootPath = './',
    ) {}

    public function applyRootPath(string $rootPath): void
    {
        $this->rootPath = $rootPath;
    }

    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, '="/') && !str_contains($content, "='/")) {
            return $content;
        }

        return preg_replace_callback(
            self::PATTERN,
            fn(array $matches): string => $matches[1] . $matches[2]
                . UrlResolver::sitePath('/' . $matches[3], $this->rootPath)
                . $matches[2],
            $content,
        ) ?? $content;
    }
}
