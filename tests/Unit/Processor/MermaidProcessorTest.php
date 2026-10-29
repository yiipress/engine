<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\Mermaid\MermaidProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class MermaidProcessorTest extends TestCase
{
    private MermaidProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new MermaidProcessor();
    }

    public function testConvertsMermaidCodeBlockToDiv(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD
    A-->B</code></pre>';

        $expected = '<div class="mermaid" tabindex="0">graph TD
    A-->B</div>';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame($expected, $result);
    }

    public function testPreservesMultilineDiagramCode(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD
    A[Start]-->B{Condition}
    B-->|Yes|C[Action 1]
    B-->|No|D[Action 2]</code></pre>';

        $expected = '<div class="mermaid" tabindex="0">graph TD
    A[Start]-->B{Condition}
    B-->|Yes|C[Action 1]
    B-->|No|D[Action 2]</div>';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame($expected, $result);
    }

    public function testIgnoresNonMermaidCodeBlocks(): void
    {
        $input = '<pre><code class="language-php">echo "Hello";</code></pre>
<pre><code class="language-mermaid">graph TD; A--&gt;B;</code></pre>
<pre><code class="language-javascript">console.log("Hi");</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('<pre><code class="language-php">', $result);
        $this->assertStringContainsString('<pre><code class="language-javascript">', $result);
        $this->assertStringContainsString('<div class="mermaid" tabindex="0">', $result);
    }

    public function testSkipsRegexWorkWhenContentHasNoMermaidMarker(): void
    {
        $input = '<p>Regular rendered HTML without mermaid blocks.</p>';

        assertSame($input, $this->processor->process($input, $this->createEntry()));
    }

    public function testHandlesMultipleMermaidBlocks(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD; A-->B;</code></pre>
<p>Some text</p>
<pre><code class="language-mermaid">sequenceDiagram; Alice-->Bob: Hello</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertEquals(
            '<div class="mermaid" tabindex="0">graph TD; A-->B;</div>
<p>Some text</p>
<div class="mermaid" tabindex="0">sequenceDiagram; Alice-->Bob: Hello</div>',
            $result,
        );
    }

    public function testKeepsDiagramSourceHtmlEscaped(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD; A--&gt;B;&amp;C</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame('<div class="mermaid" tabindex="0">graph TD; A--&gt;B;&amp;C</div>', $result);
    }

    public function testKeepsLineBreakTagsInLabels(): void
    {
        $input = '<pre><code class="language-mermaid">flowchart LR
    content["content/&lt;br&gt;Markdown"] --&gt; parse</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('content["content/&lt;br&gt;Markdown"]', $result);
    }

    public function testHeadAssetsLoadsPinnedPeleWhenMermaidPresent(): void
    {
        $content = '<div class="mermaid">graph TD; A-->B;</div>';

        $result = $this->processor->headAssets($content);

        $this->assertStringContainsString(
            '<script defer src="https://cdn.jsdelivr.net/npm/pele@1.12.0/dist/pele.global.js"'
            . ' integrity="sha384-c+QpxCAcAD72seekPP6lAUNHjqe/wUv+S2pk2yBo4ECNKMxym7WFtKKoR5xMtyhz"'
            . ' crossorigin="anonymous"></script>',
            $result,
        );
        $this->assertStringContainsString('<link rel="stylesheet" href="assets/plugins/mermaid.css">', $result);
        $this->assertStringContainsString('pele.mount(diagram, diagram.textContent);', $result);
        $this->assertStringNotContainsString('mermaid.min.js', $result);
    }

    public function testHeadAssetsReturnsEmptyStringWhenNoMermaid(): void
    {
        $content = '<p>Just a regular paragraph</p>';

        $result = $this->processor->headAssets($content);

        assertSame('', $result);
    }

    public function testDoesNotExecuteScriptInjectedViaHtmlEntities(): void
    {
        // Markdown parsers encode < > as HTML entities inside code blocks.
        // After htmlspecialchars_decode, &lt;script&gt; becomes <script> — XSS risk.
        $input = '<pre><code class="language-mermaid">&lt;script&gt;alert(\'xss\')&lt;/script&gt;</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame(
            '<div class="mermaid" tabindex="0">&lt;script&gt;alert(\'xss\')&lt;/script&gt;</div>',
            $result,
        );
    }

    public function testAssetFilesReturnsMermaidCss(): void
    {
        $files = $this->processor->assetFiles();

        $this->assertCount(1, $files);
        $sourceFile = array_key_first($files);
        $this->assertStringEndsWith('/Processor/Mermaid/assets/mermaid.css', $sourceFile);
        assertSame('assets/plugins/mermaid.css', $files[$sourceFile]);

        $css = file_get_contents($sourceFile);
        $this->assertNotFalse($css);
        $this->assertStringContainsString('--pele-bg: var(--c-bg, #fff);', $css);
        $this->assertStringContainsString('--pele-fg: var(--c-text, #1a1a1a);', $css);
        $this->assertStringContainsString('--pele-font: var(--font-sans, sans-serif);', $css);
        $this->assertStringNotContainsString('[data-theme="dark"]', $css);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_mermaid_test_');
        file_put_contents($tmp, "---\ntitle: Test\n---\nBody.");
        $this->tempFiles[] = $tmp;

        return new Entry(
            filePath: $tmp,
            collection: 'blog',
            slug: 'test',
            title: 'Test',
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
