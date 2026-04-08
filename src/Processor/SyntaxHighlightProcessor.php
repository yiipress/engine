<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Highlighter\SyntaxHighlighter;
use RuntimeException;

use function str_contains;

final class SyntaxHighlightProcessor implements ContentProcessorInterface, SiteConfigAwareProcessorInterface
{
    private string $theme = '';

    public function __construct(
        private SyntaxHighlighter $highlighter,
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
            return $this->highlighter->highlight($content, $this->theme);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to highlight code in entry \"{$entry->title}\".", 0, $e);
        }
    }
}
