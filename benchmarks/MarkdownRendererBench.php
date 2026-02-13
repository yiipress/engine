<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Render\MarkdownRenderer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
final class MarkdownRendererBench
{
    private MarkdownRenderer $renderer;
    private string $shortMarkdown;
    private string $longMarkdown;

    public function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();

        $this->shortMarkdown = "# Hello\n\nA short paragraph.\n";

        $this->longMarkdown = str_repeat(
            "## Section\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. "
            . "Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\n\n"
            . "- Item one with **bold**\n- Item two with *italic*\n- Item three with `code`\n\n"
            . "```php\n\$x = 1;\n```\n\n"
            . "| A | B |\n|---|---|\n| 1 | 2 |\n\n",
            50,
        );
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderShortMarkdown(): void
    {
        $this->renderer->render($this->shortMarkdown);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderLongMarkdown(): void
    {
        $this->renderer->render($this->longMarkdown);
    }
}
