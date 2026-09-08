<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\OutputMinifier;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class OutputMinifierTest extends TestCase
{
    public function testMinifiesInterTagAndTextWhitespace(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
                <body>
                    <h1>
                        Hello
                    </h1>
                    <p>One
                        two</p>
                </body>
            </html>
            HTML;

        assertSame('<!DOCTYPE html><html><body><h1> Hello </h1><p>One two</p></body></html>', OutputMinifier::html($html));
    }

    public function testPreservesWhitespaceSensitiveElementBodies(): void
    {
        $html = <<<'HTML'
            <html>
            <body>
            <pre title="1 > 0">Line 1
                Line 2</pre>
            <script>
                const value = "</pre>  kept  ";
            </script>
            <style>
                body {
                    white-space: pre;
                }
            </style>
            </body>
            </html>
            HTML;

        assertSame(
            <<<'HTML'
            <html><body><pre title="1 > 0">Line 1
                Line 2</pre><script>
                const value = "</pre>  kept  ";
            </script><style>
                body {
                    white-space: pre;
                }
            </style></body></html>
            HTML,
            OutputMinifier::html($html),
        );
    }

    public function testPreservesTextareaWhitespace(): void
    {
        $html = <<<'HTML'
            <div>
                <textarea data-value="1 > 0">  spaced
                    content  </textarea>
            </div>
            HTML;

        assertSame(
            <<<'HTML'
            <div><textarea data-value="1 > 0">  spaced
                    content  </textarea></div>
            HTML,
            OutputMinifier::html($html),
        );
    }

    public function testRemovesOnlyInterTagWhitespaceBeforeProtectedBlocks(): void
    {
        assertSame(
            '<p>Before</p><pre>  code  </pre>',
            OutputMinifier::html("<p>Before</p> \t\r\n\f\v<pre>  code  </pre>"),
        );
        assertSame(
            'Before <pre>  code  </pre> after',
            OutputMinifier::html('Before   <pre>  code  </pre>   after'),
        );
        assertSame(
            "<p>Before</p>\0 <pre>  code  </pre>",
            OutputMinifier::html("<p>Before</p>\0 <pre>  code  </pre>"),
        );
        assertSame(
            '<p>Before</p><pre>  code  </pre> between <textarea>  text  </textarea><p>After</p>',
            OutputMinifier::html("<p>Before</p>\n<pre>  code  </pre>\n  between  \n<textarea>  text  </textarea>\n<p>After</p>"),
        );
    }

    public function testPreservesMermaidDiagramWhitespace(): void
    {
        $html = <<<'HTML'
            <article>
                <div class="diagram mermaid" tabindex="0">flowchart LR
                    parse["Parse"] --> index["Index"]
                    index --> render["Render"]
                </div>
            </article>
            HTML;

        assertSame(
            <<<'HTML'
            <article><div class="diagram mermaid" tabindex="0">flowchart LR
                    parse["Parse"] --> index["Index"]
                    index --> render["Render"]
                </div></article>
            HTML,
            OutputMinifier::html($html),
        );
    }

    public function testPreservesMermaidDiagramWhitespaceWithGreaterThanSignInEarlierAttribute(): void
    {
        $html = <<<'HTML'
            <div data-expression="1 > 0" class="diagram mermaid">flowchart LR
                parse["Parse"] --> render["Render"]
            </div>
            HTML;

        assertSame($html, OutputMinifier::html($html));
    }

    public function testMinifiesDivWithoutExactMermaidClass(): void
    {
        assertSame(
            '<div data-class="mermaid"> Ordinary content </div>',
            OutputMinifier::html("<div data-class=\"mermaid\">\n    Ordinary content\n</div>"),
        );
        assertSame(
            '<div class="not-mermaid"> Ordinary content </div>',
            OutputMinifier::html("<div class=\"not-mermaid\">\n    Ordinary content\n</div>"),
        );
    }

    public function testKeepsTagsWithGreaterThanSignInQuotedAttributesIntact(): void
    {
        $html = <<<'HTML'
            <div>
                <a title="1 > 0" data-test='x > y'>
                    Link
                </a>
            </div>
            HTML;

        assertSame('<div><a title="1 > 0" data-test=\'x > y\'> Link </a></div>', OutputMinifier::html($html));
    }

    public function testLongOrdinaryDivAttributesDoNotExhaustBacktrackingLimit(): void
    {
        $attributes = 'data-' . str_repeat('x', 100) . '="value" class="ordinary"';

        assertSame(
            '<div ' . $attributes . '> Ordinary content </div>',
            OutputMinifier::html('<div ' . $attributes . ">\n  Ordinary   content\n</div>"),
        );
    }

    public function testPreservesMermaidAfterLongUnquotedAttribute(): void
    {
        $html = '<div data-value=' . str_repeat('x', 100) . " class='diagram mermaid'>flowchart LR\n    A --> B\n</div>";

        assertSame('<article>' . $html . '</article>', OutputMinifier::html("<article>\n  " . $html . "\n</article>"));
    }

    public function testUnclosedProtectedElementDoesNotCauseBacktracking(): void
    {
        $html = '<div><pre>' . str_repeat("Line 1\n    Line 2\n", 5000) . '<span>Done</span></div>';

        $minified = OutputMinifier::html($html);

        assertStringContainsString('<span>Done</span>', $minified);
        assertStringNotContainsString("\n", $minified);
    }
}
