<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\SearchIndexGenerator;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SearchConfig;
use App\Content\Model\SiteConfig;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertFileDoesNotExist;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;

final class SearchIndexGeneratorTest extends TestCase
{
    private string $outputDir;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-search-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        $this->tempFile = sys_get_temp_dir() . '/yiipress-search-body-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "Some body content.\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        if (is_dir($this->outputDir)) {
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
    }

    public function testDoesNotGenerateWhenSearchIsNull(): void
    {
        $siteConfig = $this->createSiteConfig(search: null);
        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, [], [], $this->outputDir);

        assertFileDoesNotExist($this->outputDir . '/search-index.json');
    }

    public function testGeneratesSearchIndexFile(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig());
        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, [], [], $this->outputDir);

        assertFileExists($this->outputDir . '/search-index.json');
    }

    public function testIndexContainsEntryData(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig());
        $collection = $this->createCollection();
        $entry = $this->createEntry(title: 'Hello World', slug: 'hello-world', tags: ['php', 'tutorial']);

        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, ['blog' => $collection], ['blog' => [$entry]], $this->outputDir);

        $items = json_decode(file_get_contents($this->outputDir . '/search-index.json'), true);

        assertSame(1, count($items));
        assertSame('Hello World', $items[0]['title']);
        assertSame('/blog/hello-world/', $items[0]['url']);
        assertSame(['php', 'tutorial'], $items[0]['tags']);
    }

    public function testIndexDoesNotIncludeBodyWhenFullTextFalse(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig(fullText: false));
        $collection = $this->createCollection();
        $entry = $this->createEntry();

        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, ['blog' => $collection], ['blog' => [$entry]], $this->outputDir);

        $items = json_decode(file_get_contents($this->outputDir . '/search-index.json'), true);

        assertSame(false, array_key_exists('body', $items[0]));
    }

    public function testIndexIncludesBodyWhenFullTextTrue(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig(fullText: true));
        $collection = $this->createCollection();
        $entry = $this->createEntry();

        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, ['blog' => $collection], ['blog' => [$entry]], $this->outputDir);

        $items = json_decode(file_get_contents($this->outputDir . '/search-index.json'), true);

        assertSame(true, array_key_exists('body', $items[0]));
        assertSame('Some body content.', $items[0]['body']);
    }

    public function testIndexIncludesStandalonePages(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig());
        $page = $this->createEntry(title: 'About Us', slug: 'about', permalink: '/about/');

        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, [], [], $this->outputDir, [$page]);

        $items = json_decode(file_get_contents($this->outputDir . '/search-index.json'), true);

        assertSame(1, count($items));
        assertSame('About Us', $items[0]['title']);
        assertSame('/about/', $items[0]['url']);
    }

    public function testEmptyIndexWhenNoEntries(): void
    {
        $siteConfig = $this->createSiteConfig(search: new SearchConfig());
        $generator = new SearchIndexGenerator();
        $generator->generate($siteConfig, [], [], $this->outputDir);

        $items = json_decode(file_get_contents($this->outputDir . '/search-index.json'), true);

        assertSame([], $items);
    }

    private function createSiteConfig(?SearchConfig $search): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: 'A test site',
            baseUrl: 'https://example.com',
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            search: $search,
        );
    }

    private function createCollection(): Collection
    {
        return new Collection(
            name: 'blog',
            title: 'Blog',
            description: '',
            permalink: '/:collection/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );
    }

    private function createEntry(
        string $title = 'Test Entry',
        string $slug = 'test-entry',
        string $permalink = '',
        array $tags = [],
    ): Entry {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: $slug,
            title: $title,
            date: null,
            draft: false,
            tags: $tags,
            categories: [],
            authors: [],
            summary: '',
            permalink: $permalink,
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
