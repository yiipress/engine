<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\CollectionConfigParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
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
        assertFalse($collection->listing);
    }
}
