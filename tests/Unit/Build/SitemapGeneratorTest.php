<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\SitemapGenerator;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class SitemapGeneratorTest extends TestCase
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
            taxonomies: ['tags', 'categories'],
            params: [],
        );

        $this->outputDir = sys_get_temp_dir() . '/yiipress-sitemap-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        $this->tempFile = sys_get_temp_dir() . '/yiipress-sitemap-body-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "Body content.\n");
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

    public function testGeneratesSitemapFile(): void
    {
        $generator = new SitemapGenerator();
        $generator->generate($this->siteConfig, [], [], $this->outputDir);

        assertFileExists($this->outputDir . '/sitemap.xml');
    }

    public function testSitemapContainsHomeUrl(): void
    {
        $generator = new SitemapGenerator();
        $generator->generate($this->siteConfig, [], [], $this->outputDir);

        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('https://test.example.com/', $xml);
    }

    public function testSitemapContainsCollectionListingUrl(): void
    {
        $generator = new SitemapGenerator();
        $collections = [
            'blog' => new Collection(
                name: 'blog',
                title: 'Blog',
                description: 'Latest posts',
                permalink: '/blog/:slug/',
                sortBy: 'date',
                sortOrder: 'desc',
                entriesPerPage: 10,
                feed: true,
                listing: true,
            ),
        ];

        $generator->generate($this->siteConfig, $collections, [], $this->outputDir);

        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('https://test.example.com/blog/', $xml);
    }

    public function testSitemapSkipsCollectionWithoutListing(): void
    {
        $generator = new SitemapGenerator();
        $collections = [
            'page' => new Collection(
                name: 'page',
                title: 'Pages',
                description: '',
                permalink: '/:slug/',
                sortBy: 'weight',
                sortOrder: 'asc',
                entriesPerPage: 0,
                feed: false,
                listing: false,
            ),
        ];

        $generator->generate($this->siteConfig, $collections, [], $this->outputDir);

        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('https://test.example.com/page/', $xml);
    }

    public function testSitemapContainsEntryUrls(): void
    {
        $generator = new SitemapGenerator();
        $collection = new Collection(
            name: 'blog',
            title: 'Blog',
            description: '',
            permalink: '/blog/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );

        $bodyLength = (int) filesize($this->tempFile);
        $entries = [
            new Entry(
                filePath: $this->tempFile,
                collection: 'blog',
                slug: 'hello-world',
                title: 'Hello World',
                date: new DateTimeImmutable('2024-03-15'),
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
                bodyLength: $bodyLength,
            ),
        ];

        $generator->generate(
            $this->siteConfig,
            ['blog' => $collection],
            ['blog' => $entries],
            $this->outputDir,
        );

        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('https://test.example.com/blog/hello-world/', $xml);
    }

    public function testSitemapContainsEntryWithCustomPermalink(): void
    {
        $generator = new SitemapGenerator();
        $collection = new Collection(
            name: 'blog',
            title: 'Blog',
            description: '',
            permalink: '/blog/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );

        $bodyLength = (int) filesize($this->tempFile);
        $entries = [
            new Entry(
                filePath: $this->tempFile,
                collection: 'blog',
                slug: 'hello-world',
                title: 'Hello World',
                date: new DateTimeImmutable('2024-03-15'),
                draft: false,
                tags: [],
                categories: [],
                authors: [],
                summary: '',
                permalink: '/custom/path/',
                layout: '',
                weight: 0,
                language: '',
                redirectTo: '',
                extra: [],
                bodyOffset: 0,
                bodyLength: $bodyLength,
            ),
        ];

        $generator->generate(
            $this->siteConfig,
            ['blog' => $collection],
            ['blog' => $entries],
            $this->outputDir,
        );

        $xml = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('https://test.example.com/custom/path/', $xml);
        assertStringNotContainsString('hello-world', $xml);
    }
}
