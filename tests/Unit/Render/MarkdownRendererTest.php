<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Render;

use YiiPress\Content\Model\MarkdownConfig;
use YiiPress\Render\MarkdownRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    public function testRendersEmptyString(): void
    {
        assertSame('', $this->renderer->render(''));
    }

    public function testRendersParagraph(): void
    {
        $html = $this->renderer->render('Hello world');

        assertSame("<p>Hello world</p>\n", $html);
    }

    public function testRendersHeading(): void
    {
        $html = $this->renderer->render('# Title');

        assertSame("<h1>Title</h1>\n", $html);
    }

    public function testRendersMultipleElements(): void
    {
        $markdown = "# Title\n\nA paragraph.\n\n- item 1\n- item 2\n";
        $html = $this->renderer->render($markdown);

        assertStringContainsString('<h1>Title</h1>', $html);
        assertStringContainsString('<p>A paragraph.</p>', $html);
        assertStringContainsString('<li>item 1</li>', $html);
        assertStringContainsString('<li>item 2</li>', $html);
    }

    public function testRendersCodeBlock(): void
    {
        $markdown = "```bash\necho hello\n```\n";
        $html = $this->renderer->render($markdown);

        assertStringContainsString('<code class="language-bash">', $html);
        assertStringContainsString('echo hello', $html);
    }

    public function testRendersTable(): void
    {
        $markdown = "| A | B |\n|---|---|\n| 1 | 2 |\n";
        $html = $this->renderer->render($markdown);

        assertStringContainsString('<table>', $html);
        assertStringContainsString('<td>1</td>', $html);
    }

    public function testKeepsEmptyTableCellsInTheirColumn(): void
    {
        $html = $this->renderer->render("| A | B | C |\n|---|---|---|\n| first || third |\n");

        assertStringContainsString("<td>first</td>\n<td></td>\n<td>third</td>", $html);
    }

    #[DataProvider('autoLinkCases')]
    public function testRendersBareLinks(string $markdown, string $expected): void
    {
        assertSame("<p>$expected</p>\n", $this->renderer->render($markdown));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function autoLinkCases(): iterable
    {
        yield 'protocol in path and sentence punctuation' => [
            'See https://example.com/go/https://example.org/a(b).',
            'See <a href="https://example.com/go/https://example.org/a(b)">'
                . 'https://example.com/go/https://example.org/a(b)</a>.',
        ];
        yield 'email with scheme' => [
            'mailto:editor@example.com',
            '<a href="mailto:editor@example.com">mailto:editor@example.com</a>',
        ];
        yield 'empty host' => ['https://.', 'https://.'];
    }

    public function testUsesFirstDecodedTokenAsCodeFenceLanguage(): void
    {
        $html = $this->renderer->render("```php&#32;extra\necho 1;\n```\n");

        assertSame("<pre><code class=\"language-php\">echo 1;\n</code></pre>\n", $html);
    }

    public function testKeepsReferenceLabelMarkersSeparateFromFollowingText(): void
    {
        $html = $this->renderer->render("[link][*ref] text*\n\n[*ref]: /page");

        assertSame("<p><a href=\"/page\">link</a> text*</p>\n", $html);
    }

    public function testKeepsIndentedParagraphAfterTwoBlankLinesInsideListItem(): void
    {
        $html = $this->renderer->render("- First paragraph\n\n\n  Second paragraph\n");

        assertSame("<ul>\n<li>\n<p>First paragraph</p>\n<p>Second paragraph</p>\n</li>\n</ul>\n", $html);
    }

    public function testRendersHeadingAfterHtmlTagWithSimilarNameToPre(): void
    {
        $html = $this->renderer->render("<pre-note>Raw text</pre-note>\n\n# Heading\n");

        assertSame("<p><pre-note>Raw text</pre-note></p>\n<h1>Heading</h1>\n", $html);
    }

    public function testRendersStrikethrough(): void
    {
        $html = $this->renderer->render('~~deleted~~');

        assertStringContainsString('<del>deleted</del>', $html);
    }

    public function testRendersTaskList(): void
    {
        $markdown = "- [x] done\n- [ ] todo\n";
        $html = $this->renderer->render($markdown);

        assertStringContainsString(<<<EXPECTED
<ul>
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled checked />done</li>
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled />todo</li>
</ul>
EXPECTED
            , $html);
    }

    public function testInsertionMarkupIsEnabledByDefault(): void
    {
        assertSame("<p><ins>added</ins></p>\n", $this->renderer->render('++added++'));
    }

    public function testInsertionMarkupCanBeExplicitlyDisabled(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(insert: false));

        assertSame("<p>++added++</p>\n", $renderer->render('++added++'));
    }

    public function testRendersInsertionMarkupWhenEnabled(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(insert: true));

        assertSame(
            "<p><del>old</del> <ins><strong>new</strong></ins> <code>++literal++</code></p>\n",
            $renderer->render('~~old~~ ++**new**++ `++literal++`'),
        );
    }

    public function testAllowsRawHtmlByDefault(): void
    {
        $html = $this->renderer->render("<section>block</section>\n\nA <span>span</span>.");

        assertStringContainsString('<section>block</section>', $html);
        assertStringContainsString('<span>span</span>', $html);
    }

    public function testEscapesRawHtmlWhenConfigured(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(
            noHtmlBlocks: true,
            noHtmlSpans: true,
        ));

        $html = $renderer->render("<section>block</section>\n\nA <span>span</span>.");

        assertStringContainsString('&lt;section&gt;block&lt;/section&gt;', $html);
        assertStringContainsString('&lt;span&gt;span&lt;/span&gt;', $html);
        assertStringNotContainsString('<section>block</section>', $html);
        assertStringNotContainsString('<span>span</span>', $html);
    }

    public function testRendersLatexMathWhenConfigured(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(latexMath: true));

        $html = $renderer->render(<<<'MARKDOWN'
Inline $x$.

$$
y
$$
MARKDOWN);

        assertStringContainsString('<span class="math">x</span>', $html);
        assertStringContainsString('<span class="math display">', $html);
        assertStringContainsString('y', $html);
    }

    public function testRendersGitHubAlertOnlyWhenConfigured(): void
    {
        $markdown = "> [!NOTE]\n> Useful information.\n";

        $disabledHtml = new MarkdownRenderer(new MarkdownConfig(admonitions: false))->render($markdown);
        $enabledHtml = new MarkdownRenderer(new MarkdownConfig(admonitions: true))->render($markdown);

        assertSame("<blockquote>\n<p>[!NOTE]\nUseful information.</p>\n</blockquote>\n", $disabledHtml);
        assertSame(
            "<div class=\"admonition-note\">\n<p class=\"admonition-title\">note</p>\n<p>Useful information.</p>\n</div>\n",
            $enabledHtml,
        );
    }

    public function testKeepsSoftBreaksByDefault(): void
    {
        $html = $this->renderer->render("first\nsecond\n");

        assertSame("<p>first\nsecond</p>\n", $html);
    }

    public function testPreservesExplicitHardBreaksByDefault(): void
    {
        $html = $this->renderer->render("first  \nsecond\n");

        assertSame("<p>first<br />\nsecond</p>\n", $html);
    }

    public function testKeepsSoftBreaksWhenHardSoftBreaksIsDisabled(): void
    {
        $markdown = "first\nsecond\n";

        $disabledHtml = new MarkdownRenderer(new MarkdownConfig(hardSoftBreaks: false))->render($markdown);
        $enabledHtml = new MarkdownRenderer(new MarkdownConfig(hardSoftBreaks: true))->render($markdown);

        assertSame("<p>first\nsecond</p>\n", $disabledHtml);
        assertSame("<p>first<br />\nsecond</p>\n", $enabledHtml);
    }
}
