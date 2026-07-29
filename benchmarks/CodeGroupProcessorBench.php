<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use DateTimeImmutable;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\Shortcode\CodeGroupProcessor;

final class CodeGroupProcessorBench
{
    private CodeGroupProcessor $processor;
    private Entry $entry;
    private string $content;

    public function __construct()
    {
        $this->processor = new CodeGroupProcessor();
        $this->entry = new Entry(
            filePath: __FILE__,
            collection: 'docs',
            slug: 'benchmark',
            title: 'Benchmark',
            date: new DateTimeImmutable('2026-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '/docs/benchmark/',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
        $this->content = str_repeat(
            "[code-group]\n[code-tab label=\"npm\"]\n```sh\nnpm install\n```\n[/code-tab]\n"
            . "[code-tab label=\"pnpm\"]\n```sh\npnpm install\n```\n[/code-tab]\n[/code-group]\n",
            25,
        );
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchPreserveShortcodes(): void
    {
        $this->processor->process($this->content, $this->entry);
    }
}
