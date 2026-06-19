<?php

declare(strict_types=1);

namespace MdParser;

final class Exception extends \RuntimeException
{
}

final readonly class Options
{
    public function __construct(
        public bool $sourcepos = false,
        public bool $hardbreaks = false,
        public bool $nobreaks = false,
        public bool $smart = false,
        public bool $unsafe = false,
        public bool $validateUtf8 = true,
        public bool $githubPreLang = true,
        public bool $liberalHtmlTag = false,
        public bool $footnotes = false,
        public bool $strikethroughDoubleTilde = false,
        public bool $tablePreferStyleAttributes = false,
        public bool $fullInfoString = false,
        public bool $tables = true,
        public bool $strikethrough = true,
        public bool $tasklist = true,
        public bool $autolink = true,
        public bool $tagfilter = true,
        public bool $headingAnchors = false,
        public bool $nofollowLinks = false,
        public bool $noIndentedCodeBlocks = false,
        public bool $permissiveAtxHeadings = false,
        public bool $collapseWhitespace = false,
        public bool $underline = false,
        public bool $highlight = false,
        public bool $superscript = false,
        public bool $subscript = false,
        public bool $spoilers = false,
        public bool $latexMath = false,
        public bool $wikiLinks = false,
        public bool $admonitions = false,
    ) {
    }

    public static function strict(): Options
    {
    }

    public static function github(): Options
    {
    }

    public static function permissive(): Options
    {
    }
}

final class Parser
{
    public readonly Options $options;

    public function __construct(?Options $options = null)
    {
    }

    public function toHtml(string $source): string
    {
    }

    public function toXml(string $source): string
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toAst(string $source): array
    {
    }

    public function toInlineHtml(string $source): string
    {
    }

    public static function html(string $source): string
    {
    }

    public static function xml(string $source): string
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function ast(string $source): array
    {
    }
}
