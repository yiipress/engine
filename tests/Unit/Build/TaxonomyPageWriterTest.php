<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Build\TaxonomyPageWriter;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class TaxonomyPageWriterTest extends TestCase
{
    private SiteConfig $siteConfig;
    private string $outputDir;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->siteConfig = new SiteConfig(
            title: 'Test Site',
            description: 'A test site',
            baseUrl: 'https://test.example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: 'john-doe',
            dateFormat: 'F j, Y',
            entriesPerPage: 2,
            permalink: '/:collection/:slug/',
            taxonomies: ['tags'],
            params: [],
        );

        $this->outputDir = sys_get_temp_dir() . '/yiipress-taxonomy-page-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        $this->tempFile = sys_get_temp_dir() . '/yiipress-taxonomy-page-body-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "Body.\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        if (!is_dir($this->outputDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputDir, FilesystemIterator::SKIP_DOTS),
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
        rmdir($this->outputDir);
    }

    public function testWritesPaginatedTaxonomyTermPages(): void
    {
        $entries = [
            $this->createEntry('post-1', 'Post 1', '2024-03-01'),
            $this->createEntry('post-2', 'Post 2', '2024-03-02'),
            $this->createEntry('post-3', 'Post 3', '2024-03-03'),
        ];
        $collections = ['blog' => $this->createCollection()];

        $writer = new TaxonomyPageWriter($this->createTemplateResolver());
        $pageCount = $writer->write(
            $this->siteConfig,
            ['tags' => ['php' => $entries]],
            $collections,
            $this->outputDir,
        );

        assertSame(3, $pageCount);
        assertFileExists($this->outputDir . '/tags/index.html');
        assertFileExists($this->outputDir . '/tags/php/index.html');
        assertFileExists($this->outputDir . '/tags/php/page/2/index.html');

        $page1 = file_get_contents($this->outputDir . '/tags/php/index.html');
        assertStringContainsString('Post 1', $page1);
        assertStringContainsString('Post 2', $page1);
        assertStringNotContainsString('Post 3', $page1);
        assertStringContainsString('Page 1 of 2', $page1);
        assertStringContainsString('rel="next"', $page1);
        assertStringNotContainsString('rel="prev"', $page1);

        $page2 = file_get_contents($this->outputDir . '/tags/php/page/2/index.html');
        assertStringContainsString('<title>php — Tags — Page 2 — Test Site</title>', $page2);
        assertStringContainsString('Post 3', $page2);
        assertStringContainsString('Page 2 of 2', $page2);
        assertStringContainsString('rel="prev"', $page2);
        assertStringNotContainsString('rel="next"', $page2);
    }

    private function createTemplateResolver(): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));

        return new TemplateResolver($registry);
    }

    private function createCollection(): Collection
    {
        return new Collection(
            name: 'blog',
            title: 'Blog',
            description: 'Latest posts',
            permalink: '/blog/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );
    }

    private function createEntry(string $slug, string $title, string $date): Entry
    {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: $slug,
            title: $title,
            date: new DateTimeImmutable($date),
            draft: false,
            tags: ['php'],
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
            bodyLength: (int) filesize($this->tempFile),
        );
    }
}
