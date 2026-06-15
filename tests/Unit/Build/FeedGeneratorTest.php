<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\FeedGenerator;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\ContentProcessorInterface;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\TagLinkProcessor;
use YiiPress\Render\MarkdownRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertStringStartsWith;
use function PHPUnit\Framework\assertFileExists;
use function substr_count;

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
            defaultLanguage: 'en',
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
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
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
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
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
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<content type="html">', $atom);
        assertStringContainsString('&lt;p&gt;First post body.&lt;/p&gt;', $atom);
    }

    public function testAtomFeedUpdatedUsesLatestEntryDate(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createEntries();

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<updated>2024-03-20T00:00:00+00:00</updated>', $atom);
    }

    public function testRssFeedContainsValidStructure(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
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
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createEntries();

        $rss = $generator->generateRss($this->siteConfig, $this->collection, $entries);

        assertStringContainsString('<title>First Post</title>', $rss);
        assertStringContainsString('<link>https://test.example.com/blog/first-post/</link>', $rss);
        assertStringContainsString('<guid>https://test.example.com/blog/first-post/</guid>', $rss);
        assertStringContainsString('<description>First post summary.</description>', $rss);
        assertStringContainsString('<pubDate>Fri, 15 Mar 2024 00:00:00 +0000</pubDate>', $rss);
        assertStringContainsString('<content:encoded>', $rss);
    }

    public function testJsonFeedContainsValidStructureAndItems(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createEntries();

        $json = $generator->generateJson($this->siteConfig, $this->collection, $entries);
        $feed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://jsonfeed.org/version/1.1', $feed['version']);
        $this->assertSame('Blog', $feed['title']);
        $this->assertSame('Latest posts', $feed['description']);
        $this->assertSame('https://test.example.com/blog/', $feed['home_page_url']);
        $this->assertSame('https://test.example.com/blog/feed.json', $feed['feed_url']);
        $this->assertSame('en', $feed['language']);
        $this->assertSame('First Post', $feed['items'][0]['title']);
        $this->assertSame('https://test.example.com/blog/first-post/', $feed['items'][0]['url']);
        $this->assertSame('First post summary.', $feed['items'][0]['summary']);
        $this->assertSame('2024-03-15T00:00:00+00:00', $feed['items'][0]['date_published']);
        $this->assertSame([['name' => 'john-doe']], $feed['items'][0]['authors']);
        $this->assertSame(['php'], $feed['items'][0]['tags']);
        assertStringContainsString('<p>First post body.</p>', $feed['items'][0]['content_html']);
    }

    public function testEmptyEntriesProducesValidFeed(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, []);
        $rss = $generator->generateRss($this->siteConfig, $this->collection, []);
        $json = $generator->generateJson($this->siteConfig, $this->collection, []);
        $feed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $atom);
        assertStringNotContainsString('<entry>', $atom);

        assertStringContainsString('<channel>', $rss);
        assertStringNotContainsString('<item>', $rss);

        $this->assertSame([], $feed['items']);
    }

    public function testDefaultFeedLimitCapsItemsAtTwenty(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createFeedEntries(25);

        $atom = $generator->generateAtom($this->siteConfig, $this->collection, $entries);
        $rss = $generator->generateRss($this->siteConfig, $this->collection, $entries);
        $json = $generator->generateJson($this->siteConfig, $this->collection, $entries);
        $feed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(20, substr_count($atom, '<entry>'));
        $this->assertSame(20, substr_count($rss, '<item>'));
        $this->assertSame(20, count($feed['items']));
        assertStringContainsString('<title>Entry 20</title>', $atom);
        assertStringNotContainsString('<title>Entry 21</title>', $atom);
        $this->assertSame('Entry 20', $feed['items'][19]['title']);
    }

    public function testCustomFeedLimitCapsItems(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $collection = $this->collectionWithFeedLimit(3);

        $rss = $generator->generateRss($this->siteConfig, $collection, $this->createFeedEntries(5));
        $json = $generator->generateJson($this->siteConfig, $collection, $this->createFeedEntries(5));
        $feed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(3, substr_count($rss, '<item>'));
        $this->assertSame(3, count($feed['items']));
        assertStringContainsString('<title>Entry 3</title>', $rss);
        assertStringNotContainsString('<title>Entry 4</title>', $rss);
        $this->assertSame('Entry 3', $feed['items'][2]['title']);
    }

    public function testZeroFeedLimitKeepsAllItems(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $collection = $this->collectionWithFeedLimit(0);

        $atom = $generator->generateAtom($this->siteConfig, $collection, $this->createFeedEntries(25));
        $rss = $generator->generateRss($this->siteConfig, $collection, $this->createFeedEntries(25));
        $json = $generator->generateJson($this->siteConfig, $collection, $this->createFeedEntries(25));
        $feed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(25, substr_count($atom, '<entry>'));
        $this->assertSame(25, substr_count($rss, '<item>'));
        $this->assertSame(25, count($feed['items']));
        assertStringContainsString('<title>Entry 25</title>', $atom);
        assertStringContainsString('<title>Entry 25</title>', $rss);
        $this->assertSame('Entry 25', $feed['items'][24]['title']);
    }

    public function testFeedFilesCanBeWrittenDirectly(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
        $entries = $this->createEntries();
        $atomPath = sys_get_temp_dir() . '/yiipress-feed-atom-' . uniqid() . '.xml';
        $rssPath = sys_get_temp_dir() . '/yiipress-feed-rss-' . uniqid() . '.xml';
        $jsonPath = sys_get_temp_dir() . '/yiipress-feed-json-' . uniqid() . '.json';

        try {
            $generator->writeAtomFile($atomPath, $this->siteConfig, $this->collection, $entries);
            $generator->writeRssFile($rssPath, $this->siteConfig, $this->collection, $entries);
            $generator->writeJsonFile($jsonPath, $this->siteConfig, $this->collection, $entries);

            assertFileExists($atomPath);
            assertFileExists($rssPath);
            assertFileExists($jsonPath);
            assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', (string) file_get_contents($atomPath));
            assertStringContainsString('<rss version="2.0"', (string) file_get_contents($rssPath));
            assertStringContainsString('"version":"https://jsonfeed.org/version/1.1"', (string) file_get_contents($jsonPath));
        } finally {
            if (is_file($atomPath)) {
                unlink($atomPath);
            }

            if (is_file($rssPath)) {
                unlink($rssPath);
            }

            if (is_file($jsonPath)) {
                unlink($jsonPath);
            }
        }
    }

    public function testCollectionWithoutDescriptionUsesSiteDescription(): void
    {
        $generator = new FeedGenerator(new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())));
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

    public function testRenderedFeedContentIsReusedAcrossAtomAndRss(): void
    {
        $processor = new class () implements ContentProcessorInterface {
            public int $calls = 0;

            public function process(string $content, Entry $entry): string
            {
                $this->calls++;

                return '<p>processed</p>';
            }
        };

        $generator = new FeedGenerator(new ContentProcessorPipeline($processor));
        $entries = [$this->createEntries()[0]];

        $generator->generateAtom($this->siteConfig, $this->collection, $entries);
        $generator->generateRss($this->siteConfig, $this->collection, $entries);
        $generator->generateJson($this->siteConfig, $this->collection, $entries);

        $this->assertSame(1, $processor->calls);
    }

    public function testInlineTagLinksUseAbsolutePublicRootInFeedContent(): void
    {
        $siteConfig = new SiteConfig(
            title: 'Project Site',
            description: 'A project site',
            baseUrl: 'https://samdark.github.io/blog/',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: 'john-doe',
            dateFormat: 'F j, Y',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: ['tags'],
            params: [],
        );
        file_put_contents($this->tempFile, "Testing #php.\n");

        $entry = new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: 'first-post',
            title: 'First Post',
            date: new DateTimeImmutable('2024-03-15'),
            draft: false,
            tags: ['php'],
            categories: [],
            authors: ['john-doe'],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: (int) filesize($this->tempFile),
        );

        $generator = new FeedGenerator(new ContentProcessorPipeline(
            new MarkdownProcessor(new MarkdownRenderer()),
            new TagLinkProcessor(),
        ));

        $atom = $generator->generateAtom($siteConfig, $this->collection, [$entry]);
        $rss = $generator->generateRss($siteConfig, $this->collection, [$entry]);

        assertStringContainsString('href=&quot;https://samdark.github.io/blog/tags/php/&quot;', $atom);
        assertStringContainsString('href=&quot;https://samdark.github.io/blog/tags/php/&quot;', $rss);
        assertStringNotContainsString('href=&quot;/tags/php/&quot;', $atom);
        assertStringNotContainsString('href=&quot;/tags/php/&quot;', $rss);
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

    /**
     * @return list<Entry>
     */
    private function createFeedEntries(int $count): array
    {
        $entries = [];
        $bodyLength = (int) filesize($this->tempFile);

        for ($i = 1; $i <= $count; $i++) {
            $entries[] = new Entry(
                filePath: $this->tempFile,
                collection: 'blog',
                slug: 'entry-' . $i,
                title: 'Entry ' . $i,
                date: new DateTimeImmutable('2024-03-' . str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT)),
                draft: false,
                tags: [],
                categories: [],
                authors: [],
                summary: 'Entry ' . $i . ' summary.',
                permalink: '',
                layout: '',
                theme: '',
                weight: 0,
                language: 'en',
                redirectTo: '',
                extra: [],
                bodyOffset: 0,
                bodyLength: $bodyLength,
            );
        }

        return $entries;
    }

    private function collectionWithFeedLimit(int $feedLimit): Collection
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
            feedLimit: $feedLimit,
        );
    }
}
