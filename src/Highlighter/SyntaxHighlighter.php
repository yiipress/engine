<?php

declare(strict_types=1);

namespace YiiPress\Highlighter;

use RuntimeException;

use function function_exists;
use function yiipress_highlight_html;
use function str_contains;

final class SyntaxHighlighter
{
    public function __construct()
    {
        if (!function_exists('yiipress_highlight_html')) {
            throw new RuntimeException('The yiipress_highlighter PHP extension is not loaded.');
        }
    }

    public function highlight(string $html, string $themeName = ''): string
    {
        if (!str_contains($html, '<pre><code class="language-')) {
            return $html;
        }

        return yiipress_highlight_html($html, $themeName) ?? $html;
    }
}
