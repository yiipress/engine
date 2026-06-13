<?php

declare(strict_types=1);

namespace YiiPress\Render;

use YiiPress\Content\Model\MarkdownConfig;

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
    private bool $footnotes;

    public function __construct(MarkdownConfig $config = new MarkdownConfig())
    {
        $this->flags = self::buildFlags($config);
        $this->footnotes = $config->footnotes;
    }

    public function render(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        if (!$this->footnotes || !str_contains($markdown, '[^')) {
            return $this->toHtml($markdown);
        }

        return $this->renderWithFootnotes($markdown);
    }

    private function renderWithFootnotes(string $markdown): string
    {
        [$markdown, $definitions] = $this->extractFootnotes($markdown);
        if ($definitions === []) {
            return $this->toHtml($markdown);
        }

        /** @var array<string, int> $used */
        $used = [];
        /** @var array<int, array{id: string, number: int}> $references */
        $references = [];
        $markdown = preg_replace_callback(
            '/\[\^([A-Za-z0-9_-]+)]/',
            static function (array $matches) use ($definitions, &$used, &$references): string {
                $id = $matches[1];
                if (!isset($definitions[$id])) {
                    return $matches[0];
                }

                $used[$id] ??= count($used) + 1;
                $reference = count($references) + 1;
                $references[$reference] = ['id' => $id, 'number' => $used[$id]];

                return "\x1FFOOTNOTE_REF:" . $reference . "\x1F";
            },
            $markdown,
        ) ?? $markdown;

        $html = $this->toHtml($markdown);
        foreach ($references as $reference => $data) {
            $id = $data['id'];
            $number = $data['number'];
            $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedReferenceId = $this->referenceId($id, $reference);
            $html = str_replace(
                "\x1FFOOTNOTE_REF:" . $reference . "\x1F",
                '<sup id="' . $escapedReferenceId . '" class="footnote-ref"><a href="#fn-' . $escapedId . '">' . $number . '</a></sup>',
                $html,
            );
        }

        if ($used === []) {
            return $html;
        }

        return $html . $this->renderFootnoteList($definitions, $used);
    }

    private function toHtml(string $markdown): string
    {
        return (string) md4c_toHtml($markdown, $this->flags);
    }

    private function referenceId(string $id, int $reference): string
    {
        $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $reference === 1 ? 'fnref-' . $escapedId : 'fnref-' . $escapedId . '-' . $reference;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function extractFootnotes(string $markdown): array
    {
        $definitions = [];
        $bodyLines = [];

        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\[\^([A-Za-z0-9_-]+)]:[ \t]*(.*)$/', $line, $matches) === 1) {
                $definitions[$matches[1]] = $matches[2];
                continue;
            }

            $bodyLines[] = $line;
        }

        return [implode("\n", $bodyLines), $definitions];
    }

    /**
     * @param array<string, string> $definitions
     * @param array<string, int> $used
     */
    private function renderFootnoteList(array $definitions, array $used): string
    {
        $html = "\n<section class=\"footnotes\" role=\"doc-endnotes\">\n<ol>\n";
        foreach ($used as $id => $_number) {
            $content = trim($this->toHtml($definitions[$id]));
            $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', $content) ?? $content;
            $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<li id="fn-' . $escapedId . '">' . $content . ' <a href="#' . $this->referenceId($id, 1) . '" class="footnote-backref" aria-label="Back to reference">Back</a></li>' . "\n";
        }

        return $html . "</ol>\n</section>\n";
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
