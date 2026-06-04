<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\EntryRenderer;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Content\Model\Author;
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
final class EntryAuthorLinksBench
{
    private string $tempDir;
    private EntryRenderer $renderer;
    private Entry $entry;
    private SiteConfig $siteConfig;
    private SiteConfig $siteConfigWithoutAuthorPages;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-author-links-bench-' . uniqid();
        mkdir($this->tempDir . '/content/blog', 0o755, true);
        mkdir($this->tempDir . '/theme', 0o755, true);

        file_put_contents(
            $this->tempDir . '/theme/entry.php',
            <<<'PHP'
<?php /** @var list<array{slug: string, title: string, url: string}> $entryAuthors */ ?>
<?php foreach ($entryAuthors as $entryAuthor): ?>
<?= $entryAuthor['url'] === '' ? $h($entryAuthor['title']) : '<a href="' . $h($entryAuthor['url']) . '">' . $h($entryAuthor['title']) . '</a>' ?>
<?php endforeach; ?>
PHP,
        );

        $entryFile = $this->tempDir . '/content/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Author Links\n---\n\nBody.\n");

        $registry = new ThemeRegistry();
        $registry->register(new Theme('bench', $this->tempDir . '/theme'));

        $this->renderer = new EntryRenderer(
            new ContentProcessorPipeline(),
            new TemplateResolver($registry),
            contentDir: $this->tempDir . '/content',
            authors: [
                'john-doe' => new Author('john-doe', 'John Doe', '', '', '', 0, 0, ''),
                'jane-doe' => new Author('jane-doe', 'Jane Doe', '', '', '', 0, 0, ''),
            ],
        );

        $this->entry = new Entry(
            filePath: $entryFile,
            collection: 'blog',
            slug: 'post',
            title: 'Author Links',
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: ['john-doe', 'jane-doe'],
            summary: '',
            permalink: '',
            layout: '',
            theme: 'bench',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 29,
            bodyLength: 6,
        );

        $this->siteConfig = $this->createSiteConfig(authorPages: true);
        $this->siteConfigWithoutAuthorPages = $this->createSiteConfig(authorPages: false);
    }

    public function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->tempDir);
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderLinkedEntryAuthors(): void
    {
        $this->renderer->render($this->siteConfig, $this->entry, '/blog/post/');
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderPlainEntryAuthors(): void
    {
        $this->renderer->render($this->siteConfigWithoutAuthorPages, $this->entry, '/blog/post/');
    }

    private function createSiteConfig(bool $authorPages): SiteConfig
    {
        return new SiteConfig(
            title: 'Bench',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            theme: 'bench',
            authorPages: $authorPages,
        );
    }
}
