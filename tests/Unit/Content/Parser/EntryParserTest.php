<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Parser;

use App\Content\Parser\EntryParser;
use App\Content\Parser\FilenameParser;
use App\Content\Parser\FrontMatterParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

final class EntryParserTest extends TestCase
{
    private EntryParser $parser;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->parser = new EntryParser(new FrontMatterParser(), new FilenameParser());
        $this->dataDir = dirname(__DIR__, 3) . '/Support/Data/content';
    }

    public function testParseEntryWithDateInFilename(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2024-03-15-test-post.md', 'blog');

        assertSame('test-post', $entry->slug);
        assertSame('Test Post', $entry->title);
        assertSame('2024-03-15', $entry->date->format('Y-m-d'));
        assertSame('blog', $entry->collection);
        assertSame(['php', 'testing'], $entry->tags);
        assertSame(['tutorials'], $entry->categories);
        assertSame(['john-doe'], $entry->authors);
        assertSame('A test post summary.', $entry->summary);
        assertFalse($entry->draft);
    }

    public function testParseEntryWithFrontMatterDateOverridesFilename(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/no-date-post.md', 'blog');

        assertSame('custom-slug', $entry->slug);
        assertSame('No Date Post', $entry->title);
        assertSame('2024-06-01', $entry->date->format('Y-m-d'));
        assertTrue($entry->draft);
        assertSame(5, $entry->weight);
        assertSame('post', $entry->layout);
        assertSame('en', $entry->language);
        assertSame('/new-url/', $entry->redirectTo);
        assertSame(['custom_field' => 'value'], $entry->extra);
    }

    public function testEntryBodyIsLoadedLazily(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2024-03-15-test-post.md', 'blog');

        $body = $entry->body();

        assertStringContainsString('This is the body of the test post.', $body);
        assertStringContainsString('It has multiple paragraphs.', $body);
    }
}
