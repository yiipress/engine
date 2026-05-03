<?php

declare(strict_types=1);

namespace YiiPress;

/**
 * Server-side syntax highlighter backed by the native highlighter extension.
 */
final class Highlighter
{
    /**
     * Creates a highlighter instance.
     *
     * The default theme is used when a highlight method does not receive an explicit theme name.
     *
     * @param string $defaultTheme Syntect theme name or .tmTheme file path to use by default.
     */
    public function __construct(string $defaultTheme = 'InspiredGitHub') {}

    /**
     * Highlights all supported code blocks in an HTML fragment.
     *
     * Code blocks are detected in the form `<pre><code class="language-...">...</code></pre>`.
     * If the fragment contains no supported blocks, the original HTML is returned unchanged.
     *
     * @param string $html HTML fragment containing escaped code blocks.
     * @param string|null $themeName Syntect theme name or .tmTheme file path. When null, the constructor default is used.
     *
     * @throws \RuntimeException When the theme is unknown or input cannot be processed.
     */
    public function highlightHtml(string $html, ?string $themeName = null): string
    {
        return $html;
    }

    /**
     * Highlights a raw code string and returns highlighted HTML.
     *
     * Unknown languages fall back to plain text highlighting. PHP code may be passed without an
     * opening `<?php` tag; the synthetic tag used internally is stripped from the returned HTML.
     *
     * @param string $code Raw source code.
     * @param string $language Syntax token such as "php", "js", or "css".
     * @param string|null $themeName Syntect theme name or .tmTheme file path. When null, the constructor default is used.
     *
     * @throws \RuntimeException When the theme is unknown or input cannot be processed.
     */
    public function highlight(string $code, string $language, ?string $themeName = null): string
    {
        return $code;
    }
}
