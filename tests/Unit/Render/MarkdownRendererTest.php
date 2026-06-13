<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Render;

use YiiPress\Content\Model\MarkdownConfig;
use YiiPress\Render\MarkdownRenderer;
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
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled checked>done</li>
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled>todo</li>
</ul>
EXPECTED
            , $html);
    }

    public function testRendersFootnotes(): void
    {
        $markdown = "Text with a note.[^note]\n\n[^note]: Footnote text.";
        $html = $this->renderer->render($markdown);

        assertStringContainsString('<sup id="fnref-note" class="footnote-ref"><a href="#fn-note">1</a></sup>', $html);
        assertStringContainsString('<section class="footnotes" role="doc-endnotes">', $html);
        assertStringContainsString('<li id="fn-note">Footnote text. <a href="#fnref-note" class="footnote-backref" aria-label="Back to reference">Back</a></li>', $html);
        assertStringNotContainsString('[^note]:', $html);
    }

    public function testLeavesFootnotesAsMarkdownWhenDisabled(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(footnotes: false));
        $markdown = "Text with a note.[^note]\n\n[^note]: Footnote text.";
        $html = $renderer->render($markdown);

        assertStringContainsString('[^note]', $html);
        assertStringContainsString('[^note]: Footnote text.', $html);
        assertStringNotContainsString('class="footnote-ref"', $html);
    }

    public function testRendersRepeatedFootnoteReferencesWithUniqueReferenceIds(): void
    {
        $markdown = "First.[^note]\n\nSecond.[^note]\n\n[^note]: Footnote text.";
        $html = $this->renderer->render($markdown);

        assertStringContainsString('<sup id="fnref-note" class="footnote-ref"><a href="#fn-note">1</a></sup>', $html);
        assertStringContainsString('<sup id="fnref-note-2" class="footnote-ref"><a href="#fn-note">1</a></sup>', $html);
        assertStringContainsString('<li id="fn-note">Footnote text. <a href="#fnref-note" class="footnote-backref" aria-label="Back to reference">Back</a></li>', $html);
    }
}
