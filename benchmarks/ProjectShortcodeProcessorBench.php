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
use YiiPress\Processor\Shortcode\ProjectShortcodeProcessor;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ProjectShortcodeProcessorBench
{
    private string $contentDir;
    private Entry $entry;
    private ProjectShortcodeProcessor $processor;
    private string $content;

    public function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-shortcode-bench-' . uniqid();
        mkdir($this->contentDir . '/blog', 0o755, true);
        mkdir($this->contentDir . '/shortcodes', 0o755, true);
        file_put_contents($this->contentDir . '/config.yaml', "title: Test\n");
        file_put_contents($this->contentDir . '/shortcodes/badge.php', '<?php return "**" . ($attributes["label"] ?? "") . "**";');

        $file = $this->contentDir . '/blog/post.md';
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

        $parts = [];
        for ($i = 1; $i <= 100; $i++) {
            $parts[] = 'Item ' . $i . ': {{< badge label="Stable" >}}';
        }

        $this->content = implode("\n", $parts);
        $this->processor = new ProjectShortcodeProcessor();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchExpandProjectShortcodes(): void
    {
        $this->processor->process($this->content, $this->entry);
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
