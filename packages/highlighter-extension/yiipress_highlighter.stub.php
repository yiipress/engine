<?php

declare(strict_types=1);

namespace YiiPress;

final class Highlighter
{
    public function __construct(string $defaultTheme = '') {}

    public function highlightHtml(string $html, ?string $themeName = null): string
    {
        return $html;
    }

    public function highlight(string $code, string $language, ?string $themeName = null): string
    {
        return $code;
    }
}
