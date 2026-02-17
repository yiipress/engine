<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\FeedGenerator;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use App\Processor\MarkdownProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertStringStartsWith;

final class FeedGeneratorTest extends TestCase
{
    private SiteConfig $siteConfig;
    private Collection $collection;
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

        $this->collection = new Collection(
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

        $this->tempFile = sys_get_temp_dir() . '/yiipress-feed-test-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "First post body.\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testAtomFeedContainsValidStructure(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $atom);
        assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $atom);
        assertStringContainsString('<title>Blog</title>', $atom);
        assertStringContainsString('<subtitle>Latest posts</subtitle>', $atom);
        assertStringContainsString('<link href="https://test.example.com/blog/"/>', $atom);
        assertStringContainsString('rel="self"', $atom);
        assertStringContainsString('application/atom+xml', $atom);
        assertStringContainsString('<id>https://test.example.com/blog/</id>', $atom);
    }

    public function testAtomFeedContainsEntries(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<title>First Post</title>', $atom);
        assertStringContainsString('<title>Second Post</title>', $atom);
        assertStringContainsString('<link href="https://test.example.com/blog/first-post/"/>', $atom);
        assertStringContainsString('<name>john-doe</name>', $atom);
        assertStringContainsString('<summary>First post summary.</summary>', $atom);
        assertStringContainsString('<published>2024-03-15T00:00:00+00:00</published>', $atom);
    }

    public function testAtomFeedContainsRenderedHtmlContent(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<content type="html">', $atom);
        assertStringContainsString('&lt;p&gt;First post body.&lt;/p&gt;', $atom);
    }

    public function testAtomFeedUpdatedUsesLatestEntryDate(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<updated>2024-03-20T00:00:00+00:00</updated>', $atom);
    }

    public function testRssFeedContainsValidStructure(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $rss = $generator->generateRss($this->siteConfig, $this->collection, $entries);

        assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $rss);
        assertStringContainsString('<rss version="2.0"', $rss);
        assertStringContainsString('xmlns:atom="http://www.w3.org/2005/Atom"', $rss);
        assertStringContainsString('xmlns:content="http://purl.org/rss/1.0/modules/content/"', $rss);
        assertStringContainsString('<title>Blog</title>', $rss);
        assertStringContainsString('<description>Latest posts</description>', $rss);
        assertStringContainsString('<link>https://test.example.com/blog/</link>', $rss);
        assertStringContainsString('<language>en</language>', $rss);
    }

    public function testRssFeedContainsItems(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $entries = $this->createEntries();

        $rss = $generator->generateRss($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<title>First Post</title>', $rss);
        assertStringContainsString('<link>https://test.example.com/blog/first-post/</link>', $rss);
        assertStringContainsString('<guid>https://test.example.com/blog/first-post/</guid>', $rss);
        assertStringContainsString('<description>First post summary.</description>', $rss);
        assertStringContainsString('<pubDate>Fri, 15 Mar 2024 00:00:00 +0000</pubDate>', $rss);
        assertStringContainsString('<content:encoded>', $rss);
    }

    public function testEmptyEntriesProducesValidFeed(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, []);
        $rss = $generator->generateRss($this->siteConfig, $this->collection, []);

        assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $atom);
        assertStringNotContainsString('<entry>', $atom);

        assertStringContainsString('<channel>', $rss);
        assertStringNotContainsString('<item>', $rss);
    }

    public function testCollectionWithoutDescriptionUsesSiteDescription(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor()));
        $collection = new Collection(
            name: 'news',
            title: 'News',
            description: '',
            permalink: '/news/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );

        $rss = $generator->generateRss($this->siteConfig, $collection, []);

        assertStringContainsString('<description>A test site</description>', $rss);
    }

    /**
     * @return list<Entry>
     */
    private function createEntries(): array
    {
        $bodyLength = (int) filesize($this->tempFile);

        return [
            new Entry(
                filePath: $this->tempFile,
                collection: 'blog',
                slug: 'first-post',
                title: 'First Post',
                date: new DateTimeImmutable('2024-03-15'),
                draft: false,
                tags: ['php'],
                categories: ['tutorials'],
                authors: ['john-doe'],
                summary: 'First post summary.',
                permalink: '',
                layout: '',
                theme: '',
                weight: 0,
                language: 'en',
                redirectTo: '',
                extra: [],
                bodyOffset: 0,
                bodyLength: $bodyLength,
            ),
            new Entry(
                filePath: $this->tempFile,
                collection: 'blog',
                slug: 'second-post',
                title: 'Second Post',
                date: new DateTimeImmutable('2024-03-20'),
                draft: false,
                tags: ['yii'],
                categories: [],
                authors: ['jane-doe'],
                summary: 'Second post summary.',
                permalink: '',
                layout: '',
                theme: '',
                weight: 0,
                language: 'en',
                redirectTo: '',
                extra: [],
                bodyOffset: 0,
                bodyLength: $bodyLength,
            ),
        ];
    }
}
