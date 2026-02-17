<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\CollectionListingWriter;
use App\Build\TemplateResolver;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class CollectionListingWriterTest extends TestCase
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
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: 'john-doe',
            dateFormat: 'F j, Y',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
        );

        $this->outputDir = sys_get_temp_dir() . '/yiipress-listing-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        $this->tempFile = sys_get_temp_dir() . '/yiipress-listing-body-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "Body.\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        if (is_dir($this->outputDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->outputDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if ($item->isDir()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }
            rmdir($this->outputDir);
        }
    }

    public function testWritesSinglePageListing(): void
    {
        $collection = $this->createCollection(entriesPerPage: 10);
        $entries = [
            $this->createEntry('first-post', 'First Post', '2024-03-15'),
            $this->createEntry('second-post', 'Second Post', '2024-03-20'),
        ];

        $writer = new CollectionListingWriter(new TemplateResolver());
        $pageCount = $writer->write($this->siteConfig, $collection, $entries, $this->outputDir);

        assertSame(1, $pageCount);
        assertFileExists($this->outputDir . '/blog/index.html');

        $html = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('Blog', $html);
        assertStringContainsString('First Post', $html);
        assertStringContainsString('Second Post', $html);
        assertStringNotContainsString('Page 1 of', $html);
    }

    public function testWritesMultiplePages(): void
    {
        $collection = $this->createCollection(entriesPerPage: 2);
        $entries = [
            $this->createEntry('post-1', 'Post 1', '2024-03-01'),
            $this->createEntry('post-2', 'Post 2', '2024-03-02'),
            $this->createEntry('post-3', 'Post 3', '2024-03-03'),
        ];

        $writer = new CollectionListingWriter(new TemplateResolver());
        $pageCount = $writer->write($this->siteConfig, $collection, $entries, $this->outputDir);

        assertSame(2, $pageCount);
        assertFileExists($this->outputDir . '/blog/index.html');
        assertFileExists($this->outputDir . '/blog/page/2/index.html');

        $page1 = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('Post 1', $page1);
        assertStringContainsString('Post 2', $page1);
        assertStringNotContainsString('Post 3', $page1);
        assertStringContainsString('Page 1 of 2', $page1);
        assertStringContainsString('rel="next"', $page1);
        assertStringNotContainsString('rel="prev"', $page1);

        $page2 = file_get_contents($this->outputDir . '/blog/page/2/index.html');
        assertStringContainsString('Post 3', $page2);
        assertStringContainsString('Page 2 of 2', $page2);
        assertStringContainsString('rel="prev"', $page2);
        assertStringNotContainsString('rel="next"', $page2);
    }

    public function testWritesEmptyListing(): void
    {
        $collection = $this->createCollection(entriesPerPage: 10);

        $writer = new CollectionListingWriter(new TemplateResolver());
        $pageCount = $writer->write($this->siteConfig, $collection, [], $this->outputDir);

        assertSame(1, $pageCount);
        assertFileExists($this->outputDir . '/blog/index.html');

        $html = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('No entries.', $html);
    }

    public function testPageTitleIncludesPageNumber(): void
    {
        $collection = $this->createCollection(entriesPerPage: 1);
        $entries = [
            $this->createEntry('post-1', 'Post 1', '2024-03-01'),
            $this->createEntry('post-2', 'Post 2', '2024-03-02'),
        ];

        $writer = new CollectionListingWriter(new TemplateResolver());
        $writer->write($this->siteConfig, $collection, $entries, $this->outputDir);

        $page1 = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('<title>Blog — Test Site</title>', $page1);

        $page2 = file_get_contents($this->outputDir . '/blog/page/2/index.html');
        assertStringContainsString('<title>Blog — Page 2 — Test Site</title>', $page2);
    }

    private function createCollection(int $entriesPerPage): Collection
    {
        return new Collection(
            name: 'blog',
            title: 'Blog',
            description: 'Latest posts',
            permalink: '/blog/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: $entriesPerPage,
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
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: (int) filesize($this->tempFile),
        );
    }
}
