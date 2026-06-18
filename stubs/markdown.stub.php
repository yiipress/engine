<?php

declare(strict_types=1);

namespace YiiPress\Markdown;

final class MarkdownRenderer
{
    public function __construct(MarkdownOptions $options = new MarkdownOptions()) {}

    public function render(string $markdown): string {}
}

final readonly class MarkdownOptions
{
    public function __construct(
        public bool $tables = true,
        public bool $strikethrough = true,
        public bool $tasklists = true,
        public bool $urlAutolinks = true,
        public bool $emailAutolinks = true,
        public bool $wwwAutolinks = true,
        public bool $collapseWhitespace = true,
        public bool $latexMath = false,
        public bool $wikilinks = false,
        public bool $underline = false,
        public bool $htmlBlocks = true,
        public bool $htmlSpans = true,
        public bool $permissiveAtxHeaders = false,
        public bool $noIndentedCodeBlocks = false,
        public bool $hardSoftBreaks = true,
        public bool $spoilers = false,
        public bool $superscripts = false,
        public bool $subscripts = false,
        public bool $admonitions = true,
        public bool $footnotes = true,
        public bool $highlight = false,
        public int $rendererFlags = 0,
    ) {}

    public function toParserFlags(): int {}
}

final class Flag
{
    public const int COLLAPSE_WHITESPACE = 0x1;
    public const int PERMISSIVE_ATX_HEADERS = 0x2;
    public const int PERMISSIVE_URL_AUTOLINKS = 0x4;
    public const int PERMISSIVE_EMAIL_AUTOLINKS = 0x8;
    public const int NO_INDENTED_CODE_BLOCKS = 0x10;
    public const int NO_HTML_BLOCKS = 0x20;
    public const int NO_HTML_SPANS = 0x40;
    public const int TABLES = 0x100;
    public const int STRIKETHROUGH = 0x200;
    public const int PERMISSIVE_WWW_AUTOLINKS = 0x400;
    public const int TASKLISTS = 0x800;
    public const int LATEX_MATH_SPANS = 0x1000;
    public const int WIKILINKS = 0x2000;
    public const int UNDERLINE = 0x4000;
    public const int HARD_SOFT_BREAKS = 0x8000;
    public const int SPOILERS = 0x10000;
    public const int SUPERSCRIPTS = 0x20000;
    public const int SUBSCRIPTS = 0x40000;
    public const int ADMONITIONS = 0x80000;
    public const int FOOTNOTES = 0x100000;
    public const int HIGHLIGHT = 0x200000;
    public const int PERMISSIVE_AUTOLINKS = self::PERMISSIVE_EMAIL_AUTOLINKS
        | self::PERMISSIVE_URL_AUTOLINKS
        | self::PERMISSIVE_WWW_AUTOLINKS;
    public const int NO_HTML = self::NO_HTML_BLOCKS | self::NO_HTML_SPANS;
}

final class Dialect
{
    public const int COMMONMARK = 0;
    public const int GITHUB = Flag::PERMISSIVE_AUTOLINKS
        | Flag::TABLES
        | Flag::STRIKETHROUGH
        | Flag::TASKLISTS
        | Flag::ADMONITIONS
        | Flag::FOOTNOTES;
}

final class HtmlFlag
{
    public const int DEBUG = 0x1;
    public const int VERBATIM_ENTITIES = 0x2;
    public const int SKIP_UTF8_BOM = 0x4;
    public const int XHTML = 0x8;
}
