<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class MarkdownConfig
{
    /**
     * @param bool $tables Enable tables extension.
     * @param bool $strikethrough Enable strikethrough extension.
     * @param bool $tasklists Enable task list extension.
     * @param bool $urlAutolinks Recognize URLs as auto-links even without '<', '>'.
     * @param bool $emailAutolinks Recognize e-mails as auto-links even without '<', '>' and 'mailto:'.
     * @param bool $wwwAutolinks Enable WWW auto-links (even without any scheme prefix, if they begin with 'www.').
     * @param bool $collapseWhitespace Collapse non-trivial whitespace into single ' '.
     * @param bool $latexMath Enable $ and $$ containing LaTeX equations.
     * @param bool $wikilinks Enable wiki links extension.
     * @param bool $underline Enable underline extension (and disables '_' for normal emphasis).
     * @param bool $noHtmlBlocks Disable raw HTML blocks.
     * @param bool $noHtmlSpans Disable raw HTML (inline).
     * @param bool $permissiveAtxHeaders Do not require space in ATX headers ( ###header ).
     * @param bool $noIndentedCodeBlocks Disable indented code blocks (Only fenced code works).
     * @param bool $hardSoftBreaks Force all soft breaks to act as hard breaks.
     */
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
        public bool $noHtmlBlocks = true,
        public bool $noHtmlSpans = true,
        public bool $permissiveAtxHeaders = false,
        public bool $noIndentedCodeBlocks = false,
        public bool $hardSoftBreaks = true,
    ) {}
}
