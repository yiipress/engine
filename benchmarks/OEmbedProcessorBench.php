<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Content\Model\Entry;
use App\Processor\OEmbed\OEmbedProcessor;
use App\Processor\Shortcode\TweetProcessor;
use App\Processor\Shortcode\VimeoProcessor;
use App\Processor\Shortcode\YouTubeProcessor;
use DateTimeImmutable;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class OEmbedProcessorBench
{
    private OEmbedProcessor $processor;
    private Entry $entry;
    private string $content;
    private string $tempFile;

    public function setUp(): void
    {
        $this->processor = new OEmbedProcessor(
            new YouTubeProcessor(),
            new VimeoProcessor(),
            new TweetProcessor(),
        );
        $this->tempFile = (string) tempnam(sys_get_temp_dir(), 'yiipress_oembed_bench_');
        file_put_contents($this->tempFile, "---\ntitle: Bench\n---\nBody.");

        $this->entry = new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
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

        $this->content = <<<TEXT
https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=45

https://vimeo.com/123456789

https://x.com/samdark/status/1234567890

Regular paragraph.
TEXT;
    }

    public function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchProcessStandaloneEmbeds(): void
    {
        $this->processor->process($this->content, $this->entry);
    }
}
