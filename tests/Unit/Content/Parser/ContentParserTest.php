<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\ContentParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

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

        assertSame(['main', 'footer'], $navigation->menuNames());
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

    public function testParseEntries(): void
    {
        $entries = iterator_to_array($this->parser->parseEntries($this->dataDir, 'blog'));

        assertCount(2, $entries);
    }

    public function testParseAuthors(): void
    {
        $authors = $this->parser->parseAuthors($this->dataDir);

        assertCount(1, $authors);
        assertArrayHasKey('john-doe', $authors);
        assertSame('John Doe', $authors['john-doe']->title);
    }
}
