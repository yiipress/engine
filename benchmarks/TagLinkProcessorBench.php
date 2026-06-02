<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\TagLinkProcessor;
use DateTimeImmutable;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

final class TagLinkProcessorBench
{
    private TagLinkProcessor $processor;
    private Entry $entry;
    private string $hashNoiseHtml;
    private string $hashtagHtml;

    public function __construct()
    {
        $this->processor = new TagLinkProcessor('/');
        $this->entry = new Entry(
            filePath: __FILE__,
            collection: 'bench',
            slug: 'tag-link',
            title: 'Tag Link',
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
        $this->hashNoiseHtml = str_repeat(
            '<p><a href="/docs/#section">Docs</a> <span style="color: #fff">Text</span></p>'
            . '<pre><span style="color:#323232;">#code</span></pre>',
            20,
        );
        $this->hashtagHtml = str_repeat(
            '<p>Follow #yii3 and #php for release notes.</p>',
            20,
        );
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchHashNoiseWithoutConvertibleTags(): void
    {
        $this->processor->process($this->hashNoiseHtml, $this->entry);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchConvertibleHashtags(): void
    {
        $this->processor->process($this->hashtagHtml, $this->entry);
    }
}
