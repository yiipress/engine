<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Highlighter;

use YiiPress\Highlighter;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function class_exists;
use function function_exists;

final class HighlighterTest extends TestCase
{
    private ?Highlighter $highlighter = null;

    protected function setUp(): void
    {
        if (!class_exists(Highlighter::class)) {
            $this->markTestSkipped('YiiPress\\Highlighter is not available.');
        }
    }

    public function testDoesNotExposeGlobalHighlightFunction(): void
    {
        assertFalse(function_exists('yiipress_highlight_html'));
    }

    public function testHighlightsPhpCodeBlock(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo &quot;hello&quot;;</code></pre>';

        $result = $this->highlighter()->highlightHtml($html);

        assertStringContainsString('style=', $result);
        assertStringNotContainsString('language-php', $result);
    }

    public function testPreservesHtmlWithoutCodeBlocks(): void
    {
        $html = '<p>No code blocks here.</p>';

        $result = $this->highlighter()->highlightHtml($html);

        assertSame($html, $result);
    }

    public function testPreservesCodeBlockWithoutLanguage(): void
    {
        $html = '<pre><code>plain code</code></pre>';

        $result = $this->highlighter()->highlightHtml($html);

        assertSame($html, $result);
    }

    public function testHighlightsMultipleCodeBlocks(): void
    {
        $html = '<p>Text</p>'
            . '<pre><code class="language-php">&lt;?php echo 1;</code></pre>'
            . '<p>Middle</p>'
            . '<pre><code class="language-yaml">title: Hello</code></pre>'
            . '<p>End</p>';

        $result = $this->highlighter()->highlightHtml($html);

        assertStringContainsString('<p>Text</p>', $result);
        assertStringContainsString('<p>Middle</p>', $result);
        assertStringContainsString('<p>End</p>', $result);
        assertStringContainsString('style=', $result);
    }

    public function testLeavesPlainCodeBlocksUntouchedWhenHighlightingLanguageBlocks(): void
    {
        $plainBlock = '<pre><code>plain code</code></pre>';
        $html = $plainBlock
            . '<pre><code class="language-php">&lt;?php echo 1;</code></pre>';

        $result = $this->highlighter()->highlightHtml($html);

        assertStringContainsString($plainBlock, $result);
        assertStringContainsString('style=', $result);
    }

    public function testHandlesEmptyString(): void
    {
        $result = $this->highlighter()->highlightHtml('');

        assertSame('', $result);
    }

    public function testHandlesUnknownLanguageGracefully(): void
    {
        $html = '<pre><code class="language-nonexistentlang">some code</code></pre>';

        $result = $this->highlighter()->highlightHtml($html);

        assertStringContainsString('some code', $result);
    }

    public function testHighlightsCodeBlockWithAdditionalAttributes(): void
    {
        $html = '<pre><code class="language-php" data-line="1">&lt;?php echo 1;</code></pre>';

        $result = $this->highlighter()->highlightHtml($html);

        assertStringContainsString('style=', $result);
        assertStringNotContainsString('data-line="1"', $result);
    }

    public function testCanUseConfiguredHighlightTheme(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo 1;</code></pre>';

        $defaultResult = $this->highlighter()->highlightHtml($html);
        $solarizedResult = $this->highlighter()->highlightHtml($html, 'Solarized (dark)');

        self::assertNotSame($defaultResult, $solarizedResult);
    }

    public function testCanUseConstructorDefaultHighlightTheme(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo 1;</code></pre>';

        $defaultResult = $this->highlighter()->highlightHtml($html);
        $solarizedResult = (new Highlighter('Solarized (dark)'))->highlightHtml($html);

        self::assertNotSame($defaultResult, $solarizedResult);
    }

    public function testThrowsForUnknownHighlightTheme(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown highlight theme');

        $this->highlighter()->highlightHtml(
            '<pre><code class="language-php">&lt;?php echo 1;</code></pre>',
            'unknown-theme',
        );
    }

    public function testHighlightsRawCode(): void
    {
        $result = $this->highlighter()->highlight('echo "hello";', 'php');

        assertStringContainsString('style=', $result);
        assertStringContainsString('hello', $result);
        assertStringNotContainsString('language-php', $result);
    }

    public function testHighlightsRawCodeWithConfiguredTheme(): void
    {
        $defaultResult = $this->highlighter()->highlight('echo 1;', 'php');
        $solarizedResult = $this->highlighter()->highlight('echo 1;', 'php', 'Solarized (dark)');

        self::assertNotSame($defaultResult, $solarizedResult);
    }

    public function testHighlightsRawCodeWithConstructorDefaultTheme(): void
    {
        $defaultResult = $this->highlighter()->highlight('echo 1;', 'php');
        $solarizedResult = (new Highlighter('Solarized (dark)'))->highlight('echo 1;', 'php');

        self::assertNotSame($defaultResult, $solarizedResult);
    }

    private function highlighter(): Highlighter
    {
        return $this->highlighter ??= new Highlighter();
    }
}
