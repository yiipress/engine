<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Build\HtmlUrlRewriter;
use YiiPress\Build\UrlResolver;
use YiiPress\Content\Model\Entry;

use function str_contains;
use function str_starts_with;

/**
 * Rewrites root-relative `href` and `src` URLs in rendered content (`/about/`) relative to the current page,
 * the same way the `$url()` template helper does, so they keep working when `base_url` has a path.
 */
final class RootRelativeLinkProcessor implements ContentProcessorInterface, RootPathAwareProcessorInterface
{
    public function __construct(
        private string $rootPath = './',
        private readonly HtmlUrlRewriter $htmlUrlRewriter = new HtmlUrlRewriter(),
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

        return $this->htmlUrlRewriter->rewrite(
            $content,
            function (string $path): string {
                if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
                    return $path;
                }

                return UrlResolver::sitePath($path, $this->rootPath);
            },
        );
    }
}
