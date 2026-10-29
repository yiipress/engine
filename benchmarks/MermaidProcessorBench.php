<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use DateTimeImmutable;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\Mermaid\MermaidProcessor;

use function str_repeat;

final class MermaidProcessorBench
{
    private MermaidProcessor $processor;
    private Entry $entry;
    private string $content;

    public function __construct()
    {
        $this->processor = new MermaidProcessor();
        $this->entry = new Entry(
            filePath: '',
            collection: 'docs',
            slug: 'bench',
            title: 'Bench',
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
        $this->content = str_repeat(
            '<p>Text before the diagram.</p>' . "\n"
            . '<pre><code class="language-mermaid">flowchart LR' . "\n"
            . '    content["content/&lt;br&gt;Markdown, YAML, assets"] --&gt; parse["Parse"]' . "\n"
            . '    parse --&gt; index["Index"]' . "\n"
            . '    index --&gt; render["Render"]' . "\n"
            . '    render --&gt; write["Write"]</code></pre>' . "\n",
            20,
        );
    }

    #[Revs(1_000)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchProcess(): void
    {
        $this->processor->process($this->content, $this->entry);
    }
}
