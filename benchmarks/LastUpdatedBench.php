<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\EntryRenderer;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class LastUpdatedBench
{
    private string $tempDir;
    private EntryRenderer $renderer;
    private Entry $enabledEntry;
    private Entry $disabledEntry;
    private SiteConfig $siteConfig;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-last-updated-bench-' . uniqid();
        $entryFile = $this->tempDir . '/content/post.md';
        $themeDir = $this->tempDir . '/theme';
        mkdir($themeDir, 0o755, true);
        mkdir(dirname($entryFile), 0o755, true);
        file_put_contents($entryFile, "Body.\n");
        file_put_contents(
            $themeDir . '/entry.php',
            '<?php if ($lastUpdated !== null): ?><time><?= $lastUpdated["iso"] ?></time><?php endif; ?>',
        );

        $registry = new ThemeRegistry();
        $registry->register(new Theme('bench', $themeDir));
        $this->renderer = new EntryRenderer(
            new ContentProcessorPipeline(),
            new TemplateResolver($registry),
            contentDir: dirname($entryFile),
        );
        $this->enabledEntry = $this->createEntry($entryFile, true);
        $this->disabledEntry = $this->createEntry($entryFile, false);
        $this->siteConfig = new SiteConfig(
            title: 'Bench',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:slug/',
            taxonomies: [],
            params: [],
            theme: 'bench',
            lastUpdated: true,
        );
    }

    public function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tempDir);
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchEnabledEntry(): void
    {
        $this->renderer->render($this->siteConfig, $this->enabledEntry);
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchDisabledEntry(): void
    {
        $this->renderer->render($this->siteConfig, $this->disabledEntry);
    }

    private function createEntry(string $filePath, bool $lastUpdated): Entry
    {
        return new Entry(
            filePath: $filePath,
            collection: 'docs',
            slug: 'post',
            title: 'Post',
            date: new DateTimeImmutable('2026-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: 'bench',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 6,
            lastUpdated: $lastUpdated,
        );
    }
}
