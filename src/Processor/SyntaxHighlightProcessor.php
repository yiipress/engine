<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Highlighter;
use RuntimeException;

use function htmlspecialchars;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function strtolower;
use function strtoupper;

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

        $content = $this->addLanguageMetadata($content);

        try {
            return $this->highlighter->highlightHtml($content, $this->theme === '' ? null : $this->theme);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to highlight code in entry \"{$entry->title}\".", 0, $e);
        }
    }

    private function addLanguageMetadata(string $content): string
    {
        return preg_replace_callback(
            '~<pre><code class=[^a-zA-Z0-9]*language-([a-zA-Z0-9_+.#-]+)[^>]*>.*?</code></pre>~s',
            static function (array $matches): string {
                $language = strtolower($matches[1]);
                $label = strtoupper($language);

                return sprintf(
                    '<div class="code-block" data-language="%s"><span class="code-language-label">%s</span>%s</div>',
                    htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    $matches[0],
                );
            },
            $content,
        ) ?? $content;
    }
}
