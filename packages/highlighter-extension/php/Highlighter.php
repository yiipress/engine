<?php

declare(strict_types=1);

namespace YiiPress;

use RuntimeException;

use function function_exists;
use function str_contains;
use function yiipress_highlight_html;

final class Highlighter
{
    public function __construct(
        private readonly string $defaultTheme = '',
    ) {}

    public static function isAvailable(): bool
    {
        return function_exists('yiipress_highlight_html');
    }

    public function highlight(string $html, ?string $theme = null): string
    {
        if (!str_contains($html, '<pre><code class="language-')) {
            return $html;
        }

        if (!self::isAvailable()) {
            throw new RuntimeException('The yiipress_highlighter PHP extension is not loaded.');
        }

        return yiipress_highlight_html($html, $theme ?? $this->defaultTheme) ?? $html;
    }
}
