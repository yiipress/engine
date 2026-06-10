<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use YiiPress\Content\Parser\CollectionConfigParser;
use YiiPress\Content\Parser\InvalidContentConfigException;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

final class CollectionConfigParserTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = dirname(__DIR__, 3) . '/Support/Data/content';
    }

    public function testParseBlogCollection(): void
    {
        $parser = new CollectionConfigParser();

        $collection = $parser->parse($this->dataDir . '/blog/_collection.yaml', 'blog');

        assertSame('blog', $collection->name);
        assertSame('Blog', $collection->title);
        assertSame('Latest posts', $collection->description);
        assertSame('/blog/:slug/', $collection->permalink);
        assertSame('date', $collection->sortBy);
        assertSame('desc', $collection->sortOrder);
        assertSame(10, $collection->entriesPerPage);
        assertTrue($collection->feed);
        assertSame(20, $collection->feedLimit);
        assertTrue($collection->listing);
    }

    public function testParsePageCollection(): void
    {
        $parser = new CollectionConfigParser();

        $collection = $parser->parse($this->dataDir . '/page/_collection.yaml', 'page');

        assertSame('page', $collection->name);
        assertSame('Pages', $collection->title);
        assertSame('/:slug/', $collection->permalink);
        assertSame('weight', $collection->sortBy);
        assertSame(0, $collection->entriesPerPage);
        assertFalse($collection->feed);
        assertTrue($collection->listing);
        assertSame(['faq', 'about'], $collection->order);
    }

    public function testParsesNavigationPagerOption(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-collection-');
        self::assertNotFalse($file);
        file_put_contents($file, "title: Docs\nnavigation_pager: true\n");

        try {
            $collection = (new CollectionConfigParser())->parse($file, 'docs');

            assertTrue($collection->navigationPager);
        } finally {
            unlink($file);
        }
    }

    public function testParsesFeedLimitOption(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-collection-');
        self::assertNotFalse($file);
        file_put_contents($file, "title: Blog\nfeed: true\nfeed_limit: 5\n");

        try {
            $collection = (new CollectionConfigParser())->parse($file, 'blog');

            assertSame(5, $collection->feedLimit);
        } finally {
            unlink($file);
        }
    }

    public function testThrowsFriendlyExceptionWhenConfigIsNotMapping(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-collection-');
        self::assertNotFalse($file);
        file_put_contents($file, "- title\n");

        try {
            (new CollectionConfigParser())->parse($file, 'blog');
            $this->fail('Expected invalid content configuration exception.');
        } catch (InvalidContentConfigException $e) {
            assertSame('Invalid content configuration', $e->getName());
            assertSame('The collection configuration file must contain YAML key-value pairs.', $e->getMessage());
            assertStringContainsString('permalink: /blog/:slug/', (string) $e->getSolution());
        } finally {
            unlink($file);
        }
    }
}
