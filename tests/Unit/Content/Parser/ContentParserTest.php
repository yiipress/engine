<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use YiiPress\Content\Parser\ContentParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class ContentParserTest extends TestCase
{
    private ContentParser $parser;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->dataDir = dirname(__DIR__, 3) . '/Support/Data/content';
    }

    public function testParseSiteConfig(): void
    {
        $config = $this->parser->parseSiteConfig($this->dataDir);

        assertSame('Test Site', $config->title);
    }

    public function testParseNavigation(): void
    {
        $navigation = $this->parser->parseNavigation($this->dataDir);

        assertSame(['main', 'footer', 'sidebar'], $navigation->menuNames());
    }

    public function testParseCollections(): void
    {
        $collections = $this->parser->parseCollections($this->dataDir);

        assertCount(2, $collections);
        assertArrayHasKey('blog', $collections);
        assertArrayHasKey('page', $collections);
        assertSame('Blog', $collections['blog']->title);
        assertSame('Pages', $collections['page']->title);
    }

    public function testDataDirectoryIsNotParsedAsCollection(): void
    {
        $dir = sys_get_temp_dir() . '/yiipress-content-data-' . uniqid();
        mkdir($dir . '/data', 0o755, true);
        file_put_contents($dir . '/data/_collection.yaml', "title: Data\n");

        try {
            assertSame([], $this->parser->parseCollections($dir));
            assertSame([], iterator_to_array($this->parser->parseAllEntries($dir), false));
        } finally {
            unlink($dir . '/data/_collection.yaml');
            rmdir($dir . '/data');
            rmdir($dir);
        }
    }

    public function testParseRootCollection(): void
    {
        $dir = sys_get_temp_dir() . '/yiipress-root-collection-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/_collection.yaml', "navigation_pager: true\n");

        try {
            $collection = $this->parser->parseRootCollection($dir);

            assertSame('', $collection->name);
            assertTrue($collection->navigationPager);
            assertFalse($collection->listing);
        } finally {
            unlink($dir . '/_collection.yaml');
            rmdir($dir);
        }
    }

    public function testParseEntries(): void
    {
        $entries = iterator_to_array($this->parser->parseEntries($this->dataDir, 'blog'));

        assertCount(7, $entries);
    }

    public function testParseAuthors(): void
    {
        $authors = iterator_to_array($this->parser->parseAuthors($this->dataDir));

        assertCount(1, $authors);
        assertArrayHasKey('john-doe', $authors);
        assertSame('John Doe', $authors['john-doe']->title);
    }

    public function testParseAllEntries(): void
    {
        $entries = iterator_to_array($this->parser->parseAllEntries($this->dataDir), false);

        assertCount(9, $entries);
    }
}
