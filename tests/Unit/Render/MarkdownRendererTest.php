<?php

declare(strict_types=1);

namespace App\Tests\Unit\Render;

use App\Render\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

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

        assertStringContainsString('[x]', $html);
        assertStringContainsString('[ ]', $html);
    }
}
