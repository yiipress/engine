<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\MetaTagsBuilder;
use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Content\Model\SiteConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function file_put_contents;

final class MetaTagsBuilderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'yiipress-meta-test-');
        file_put_contents($this->tempFile, "---\ntitle: Test\n---\nBody content.");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testForEntryBuildsArticleMetaTags(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com');
        $entry = $this->createEntry(title: 'Hello World', permalink: '/blog/hello-world/');

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/hello-world/');

        $this->assertSame('Hello World', $metaTags->title);
        $this->assertSame('article', $metaTags->type);
        $this->assertSame('https://example.com/blog/hello-world/', $metaTags->canonicalUrl);
        $this->assertSame('summary', $metaTags->twitterCard);
    }

    public function testForEntryUsesEntrySummaryAsDescription(): void
    {
        $siteConfig = $this->createSiteConfig();
        $entry = $this->createEntry(summary: 'A custom summary.');

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('A custom summary.', $metaTags->description);
    }

    public function testForEntryWithAbsoluteEntryImage(): void
    {
        $siteConfig = $this->createSiteConfig();
        $entry = $this->createEntry(image: 'https://cdn.example.com/img.jpg');

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('https://cdn.example.com/img.jpg', $metaTags->image);
        $this->assertSame('summary_large_image', $metaTags->twitterCard);
    }

    public function testForEntryWithRelativeEntryImagePrependsBaseUrl(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com');
        $entry = $this->createEntry(image: '/images/hero.jpg');

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('https://example.com/images/hero.jpg', $metaTags->image);
    }

    public function testForEntryFallsBackToSiteImage(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com', image: '/assets/og.png');
        $entry = $this->createEntry();

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('https://example.com/assets/og.png', $metaTags->image);
        $this->assertSame('summary_large_image', $metaTags->twitterCard);
    }

    public function testForEntryWithNoImageHasSummaryCard(): void
    {
        $siteConfig = $this->createSiteConfig();
        $entry = $this->createEntry();

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('', $metaTags->image);
        $this->assertSame('summary', $metaTags->twitterCard);
    }

    public function testForEntryIncludesTwitterSite(): void
    {
        $siteConfig = $this->createSiteConfig(twitterSite: '@example');
        $entry = $this->createEntry();

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('@example', $metaTags->twitterSite);
    }

    public function testForEntryWithEmptyBaseUrlProducesEmptyCanonical(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: '');
        $entry = $this->createEntry();

        $metaTags = MetaTagsBuilder::forEntry($siteConfig, $entry, '/blog/post/');

        $this->assertSame('', $metaTags->canonicalUrl);
    }

    public function testForPageBuildsWebsiteMetaTags(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com');

        $metaTags = MetaTagsBuilder::forPage($siteConfig, 'Blog', 'Latest posts', '/blog/');

        $this->assertSame('Blog', $metaTags->title);
        $this->assertSame('Latest posts', $metaTags->description);
        $this->assertSame('website', $metaTags->type);
        $this->assertSame('https://example.com/blog/', $metaTags->canonicalUrl);
    }

    public function testForPageUsesSiteImageWhenSet(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com', image: '/og.png');

        $metaTags = MetaTagsBuilder::forPage($siteConfig, 'Blog', '', '/blog/');

        $this->assertSame('https://example.com/og.png', $metaTags->image);
        $this->assertSame('summary_large_image', $metaTags->twitterCard);
    }

    private function createSiteConfig(
        string $baseUrl = 'https://example.com',
        string $image = '',
        string $twitterSite = '',
    ): SiteConfig {
        return new SiteConfig(
            title: 'Test Site',
            description: 'A test site.',
            baseUrl: $baseUrl,
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            markdown: new MarkdownConfig(),
            theme: '',
            image: $image,
            twitterSite: $twitterSite,
        );
    }

    private function createEntry(
        string $title = 'Test Post',
        string $summary = '',
        string $image = '',
        string $permalink = '',
    ): Entry {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: 'test-post',
            title: $title,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: $summary,
            permalink: $permalink,
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
            image: $image,
        );
    }
}
