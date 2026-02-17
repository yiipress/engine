<?php

declare(strict_types=1);

namespace App\Tests\Unit\Highlighter;

use App\Highlighter\SyntaxHighlighter;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class SyntaxHighlighterTest extends TestCase
{
    private SyntaxHighlighter $highlighter;

    protected function setUp(): void
    {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('ext-ffi is not available.');
        }

        if (!file_exists('/usr/local/lib/libyiipress_highlighter.so')) {
            $this->markTestSkipped('libyiipress_highlighter.so is not available.');
        }

        $this->highlighter = new SyntaxHighlighter();
    }

    public function testHighlightsPhpCodeBlock(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo &quot;hello&quot;;</code></pre>';

        $result = $this->highlighter->highlight($html);

        assertStringContainsString('style=', $result);
        assertStringNotContainsString('language-php', $result);
    }

    public function testPreservesHtmlWithoutCodeBlocks(): void
    {
        $html = '<p>No code blocks here.</p>';

        $result = $this->highlighter->highlight($html);

        assertSame($html, $result);
    }

    public function testPreservesCodeBlockWithoutLanguage(): void
    {
        $html = '<pre><code>plain code</code></pre>';

        $result = $this->highlighter->highlight($html);

        assertSame($html, $result);
    }

    public function testHighlightsMultipleCodeBlocks(): void
    {
        $html = '<p>Text</p>'
            . '<pre><code class="language-php">&lt;?php echo 1;</code></pre>'
            . '<p>Middle</p>'
            . '<pre><code class="language-yaml">title: Hello</code></pre>'
            . '<p>End</p>';

        $result = $this->highlighter->highlight($html);

        assertStringContainsString('<p>Text</p>', $result);
        assertStringContainsString('<p>Middle</p>', $result);
        assertStringContainsString('<p>End</p>', $result);
        assertStringContainsString('style=', $result);
    }

    public function testHandlesEmptyString(): void
    {
        $result = $this->highlighter->highlight('');

        assertSame('', $result);
    }

    public function testHandlesUnknownLanguageGracefully(): void
    {
        $html = '<pre><code class="language-nonexistentlang">some code</code></pre>';

        $result = $this->highlighter->highlight($html);

        assertStringContainsString('some code', $result);
    }
}
