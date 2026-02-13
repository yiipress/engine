<?php

declare(strict_types=1);

namespace App\Render;

use App\Content\Model\MarkdownConfig;

use function md4c_toHtml;

final class MarkdownRenderer
{
    private const int MD_FLAG_COLLAPSEWHITESPACE = 0x0001;
    private const int MD_FLAG_NOHTMLSPANS = 0x0008;
    private const int MD_FLAG_NOHTMLBLOCKS = 0x0010;
    private const int MD_FLAG_TABLES = 0x0100;
    private const int MD_FLAG_STRIKETHROUGH = 0x0200;
    private const int MD_FLAG_PERMISSIVEURLAUTOLINKS = 0x0400;
    private const int MD_FLAG_LATEXMATHSPANS = 0x1000;
    private const int MD_FLAG_TASKLISTS = 0x2000;
    private const int MD_FLAG_WIKILINKS = 0x4000;
    private const int MD_FLAG_UNDERLINE = 0x8000;

    private int $flags;

    public function __construct(MarkdownConfig $config = new MarkdownConfig())
    {
        $this->flags = self::buildFlags($config);
    }

    public function render(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        return md4c_toHtml($markdown, $this->flags);
    }

    private static function buildFlags(MarkdownConfig $config): int
    {
        $flags = 0;

        if ($config->tables) {
            $flags |= self::MD_FLAG_TABLES;
        }
        if ($config->strikethrough) {
            $flags |= self::MD_FLAG_STRIKETHROUGH;
        }
        if ($config->tasklists) {
            $flags |= self::MD_FLAG_TASKLISTS;
        }
        if ($config->autolinks) {
            $flags |= self::MD_FLAG_PERMISSIVEURLAUTOLINKS;
        }
        if ($config->collapseWhitespace) {
            $flags |= self::MD_FLAG_COLLAPSEWHITESPACE;
        }
        if ($config->latexMath) {
            $flags |= self::MD_FLAG_LATEXMATHSPANS;
        }
        if ($config->wikilinks) {
            $flags |= self::MD_FLAG_WIKILINKS;
        }
        if ($config->underline) {
            $flags |= self::MD_FLAG_UNDERLINE;
        }
        if (!$config->htmlBlocks) {
            $flags |= self::MD_FLAG_NOHTMLBLOCKS;
        }
        if (!$config->htmlSpans) {
            $flags |= self::MD_FLAG_NOHTMLSPANS;
        }

        return $flags;
    }
}
