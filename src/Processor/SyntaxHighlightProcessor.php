<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Highlighter;
use RuntimeException;

use function str_contains;

final class SyntaxHighlightProcessor implements ContentProcessorInterface, SiteConfigAwareProcessorInterface
{
    private string $theme = '';

    public function __construct(
        private Highlighter $highlighter,
    ) {}

    public function applySiteConfig(SiteConfig $siteConfig): void
    {
        $this->theme = $siteConfig->highlightTheme;
    }

    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, '<pre><code class="language-')) {
            return $content;
        }

        try {
            return $this->highlighter->highlightHtml($content, $this->theme === '' ? null : $this->theme);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to highlight code in entry \"{$entry->title}\".", 0, $e);
        }
    }
}
