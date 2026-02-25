<?php

declare(strict_types=1);

namespace App\Render;

use App\Content\Model\MarkdownConfig;

use function md4c_toHtml;

final class MarkdownRenderer
{
    /** Collapse non-trivial whitespace into single ' '. */
    private const int MD_FLAG_COLLAPSEWHITESPACE = 0x0001;

    /** Do not require space in ATX headers ( ###header ) */
    private const int MD_FLAG_PERMISSIVEATXHEADERS = 0x0002;

    /** Recognize URLs as autolinks even without '<', '>' */
    private const int MD_FLAG_PERMISSIVEURLAUTOLINKS = 0x0004;

    /** Recognize e-mails as autolinks even without '<', '>' and 'mailto:' */
    private const int MD_FLAG_PERMISSIVEEMAILAUTOLINKS = 0x0008;

    /** Disable indented code blocks. (Only fenced code works.) */
    private const int MD_FLAG_NOINDENTEDCODEBLOCKS = 0x0010;

    /** Disable raw HTML blocks. */
    private const int MD_FLAG_NOHTMLBLOCKS = 0x0020;

    /** Disable raw HTML (inline). */
    private const int MD_FLAG_NOHTMLSPANS = 0x0040;

    /** Enable tables extension. */
    private const int MD_FLAG_TABLES = 0x0100;

    /** Enable strikethrough extension. */
    private const int MD_FLAG_STRIKETHROUGH = 0x0200;

    /** Enable WWW autolinks (even without any scheme prefix, if they begin with 'www.') */
    private const int MD_FLAG_PERMISSIVEWWWAUTOLINKS = 0x0400;

    /** Enable task list extension. */
    private const int MD_FLAG_TASKLISTS = 0x0800;

    /** Enable $ and $$ containing LaTeX equations. */
    private const int MD_FLAG_LATEXMATHSPANS = 0x1000;

    /** Enable wiki links extension. */
    private const int MD_FLAG_WIKILINKS = 0x2000;

    /** Enable underline extension (and disables '_' for normal emphasis). */
    private const int MD_FLAG_UNDERLINE = 0x4000;

    /** Force all soft breaks to act as hard breaks. */
    private const int MD_FLAG_HARD_SOFT_BREAKS = 0x8000;
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
        if ($config->urlAutolinks) {
            $flags |= self::MD_FLAG_PERMISSIVEURLAUTOLINKS;
        }
        if ($config->emailAutolinks) {
            $flags |= self::MD_FLAG_PERMISSIVEEMAILAUTOLINKS;
        }
        if ($config->wwwAutolinks) {
            $flags |= self::MD_FLAG_PERMISSIVEWWWAUTOLINKS;
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
        if (!$config->noHtmlBlocks) {
            $flags |= self::MD_FLAG_NOHTMLBLOCKS;
        }
        if (!$config->noHtmlSpans) {
            $flags |= self::MD_FLAG_NOHTMLSPANS;
        }
        if ($config->permissiveAtxHeaders) {
            $flags |= self::MD_FLAG_PERMISSIVEATXHEADERS;
        }
        if ($config->noIndentedCodeBlocks) {
            $flags |= self::MD_FLAG_NOINDENTEDCODEBLOCKS;
        }
        if ($config->hardSoftBreaks) {
            $flags |= self::MD_FLAG_HARD_SOFT_BREAKS;
        }

        return $flags;
    }
}
