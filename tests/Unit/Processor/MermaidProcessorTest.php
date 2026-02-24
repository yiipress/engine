<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\Mermaid\MermaidProcessor;
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

        $expected = '<div class="mermaid">graph TD
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

        $expected = '<div class="mermaid">graph TD
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
        $this->assertStringContainsString('<div class="mermaid">', $result);
    }

    public function testHandlesMultipleMermaidBlocks(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD; A-->B;</code></pre>
<p>Some text</p>
<pre><code class="language-mermaid">sequenceDiagram; Alice-->Bob: Hello</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertEquals(
            '<div class="mermaid">graph TD; A-->B;</div>
<p>Some text</p>
<div class="mermaid">sequenceDiagram; Alice-->Bob: Hello</div>',
            $result,
        );
    }

    public function testDecodesHtmlEntities(): void
    {
        $input = '<pre><code class="language-mermaid">graph TD; A--&gt;B;&amp;C</code></pre>';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('A-->B;&C', $result);
    }

    public function testHeadAssetsReturnsHtmlWhenMermaidPresent(): void
    {
        $content = '<div class="mermaid">graph TD; A-->B;</div>';

        $result = $this->processor->headAssets($content);

        $this->assertStringContainsString('mermaid.min.js', $result);
        $this->assertStringContainsString('mermaid.css', $result);
        $this->assertStringContainsString('initializeMermaid', $result);
    }

    public function testHeadAssetsReturnsEmptyStringWhenNoMermaid(): void
    {
        $content = '<p>Just a regular paragraph</p>';

        $result = $this->processor->headAssets($content);

        assertSame('', $result);
    }

    public function testAssetFilesReturnsMermaidCss(): void
    {
        $files = $this->processor->assetFiles();

        $this->assertCount(1, $files);
        $sourceFile = array_key_first($files);
        $this->assertStringEndsWith('/Processor/Mermaid/assets/mermaid.css', $sourceFile);
        assertSame('assets/plugins/mermaid.css', $files[$sourceFile]);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_mermaid_test_');
        file_put_contents($tmp, "---\ntitle: Test\n---\nBody.");

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
}
