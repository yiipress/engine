<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\OutputMinifier;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

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
            <pre>Line 1
                Line 2</pre>
            <script>
                const value = "  kept  ";
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
            <html><body><pre>Line 1
                Line 2</pre><script>
                const value = "  kept  ";
            </script><style>
                body {
                    white-space: pre;
                }
            </style></body></html>
            HTML,
            OutputMinifier::html($html),
        );
    }
}
