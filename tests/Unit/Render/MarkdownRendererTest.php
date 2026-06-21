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
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled checked />done</li>
<li class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled />todo</li>
</ul>
EXPECTED
        , $html);
    }

    public function testEscapesRawHtmlByDefault(): void
    {
        $html = $this->renderer->render("<section>block</section>\n\nA <span>span</span>.");

        assertStringContainsString('&lt;section&gt;block&lt;/section&gt;', $html);
        assertStringContainsString('&lt;span&gt;span&lt;/span&gt;', $html);
        assertStringNotContainsString('<section>block</section>', $html);
        assertStringNotContainsString('<span>span</span>', $html);
    }

    public function testAllowsRawHtmlWhenConfigured(): void
    {
        $renderer = new MarkdownRenderer(new MarkdownConfig(
            noHtmlBlocks: false,
            noHtmlSpans: false,
        ));

        $html = $renderer->render("<section>block</section>\n\nA <span>span</span>.");

        assertStringContainsString('<section>block</section>', $html);
        assertStringContainsString('<span>span</span>', $html);
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
}
