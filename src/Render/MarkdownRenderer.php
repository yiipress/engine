<?php

declare(strict_types=1);

namespace App\Render;

use function md4c_toHtml;

final class MarkdownRenderer
{
    private const int MD_FLAG_TABLES = 0x0100;
    private const int MD_FLAG_STRIKETHROUGH = 0x0200;
    private const int MD_FLAG_TASKLISTS = 0x2000;
    private const int MD_FLAG_PERMISSIVEAUTOLINKS = 0x0400;

    private const int PARSER_FLAGS = self::MD_FLAG_TABLES
        | self::MD_FLAG_STRIKETHROUGH
        | self::MD_FLAG_TASKLISTS
        | self::MD_FLAG_PERMISSIVEAUTOLINKS;

    public function render(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        return md4c_toHtml($markdown, self::PARSER_FLAGS);
    }
}
