<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use DateTimeImmutable;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\ProjectProcessorLoader;
use YiiPress\Processor\TagLinkProcessor;
use YiiPress\Render\MarkdownRenderer;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ProjectProcessorBench
{
    private string $contentDir;
    private Entry $entry;
    private ContentProcessorPipeline $pipeline;

    public function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-project-processor-bench-' . uniqid() . '/content';
        mkdir($this->contentDir . '/processors', 0o755, true);
        file_put_contents($this->contentDir . '/config.yaml', "title: Test\nlanguages: [en]\n");
        file_put_contents(
            $this->contentDir . '/processors/badge.php',
            '<?php return static fn(string $content): string => str_replace("[badge]", "**Stable**", $content);',
        );

        $file = $this->contentDir . '/post.md';
        file_put_contents($file, "---\ntitle: Test\n---\nBody.");
        $this->entry = new Entry(
            filePath: $file,
            collection: 'blog',
            slug: 'post',
            title: 'Post',
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

        $processors = (new ProjectProcessorLoader($this->contentDir, $this->contentDir . '/config.yaml'))
            ->load(new ProcessorConfig());
        $this->pipeline = new ContentProcessorPipeline(
            new MarkdownProcessor(new MarkdownRenderer()),
            new TagLinkProcessor(),
        );
        $this->pipeline->insertBefore(MarkdownProcessor::class, ...$processors->contentBeforeMarkdown);
        $this->pipeline->insertAfter(MarkdownProcessor::class, ...$processors->contentAfterMarkdown);
    }

    public function tearDown(): void
    {
        $this->removeDir(dirname($this->contentDir));
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchProcessProjectProcessor(): void
    {
        $this->pipeline->process('Status: [badge]', $this->entry);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
