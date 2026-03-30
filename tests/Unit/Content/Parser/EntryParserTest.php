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
        assertSame('A test post summary.', $entry->summary());
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

    public function testInlineTagsAreExtractedFromBody(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2026-03-30-inline-tags.md', 'blog');

        // Should have front matter tag 'php' plus inline tags 'inline', 'shit', 'yii'
        // 'php' should not be duplicated (it's in both front matter and body)
        // Inline tags are normalized to lowercase
        assertSame(['php', 'inline', 'shit', 'yii'], $entry->tags);
    }

    public function testHtmlColorCodesAreNotExtractedAsTags(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2026-03-30-inline-tags.md', 'blog');

        // #f00 and #aabbcc are CSS color codes inside HTML attributes and must not appear as tags
        $tagLowercases = array_map(strtolower(...), $entry->tags);
        assertFalse(in_array('f00', $tagLowercases, true));
        assertFalse(in_array('aabbcc', $tagLowercases, true));
    }

    public function testInlineTagsAreCaseInsensitiveMerged(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2026-03-30-inline-tags.md', 'blog');

        // #php in body should be recognized as duplicate of 'php' in front matter (case-insensitive)
        // so it doesn't appear twice in the tags list
        $tagLowercases = array_map(strtolower(...), $entry->tags);
        assertSame(['php', 'inline', 'shit', 'yii'], $tagLowercases);

        // Verify 'php' appears only once (not duplicated as 'php' and 'PHP')
        $phpCount = count(array_filter($entry->tags, static fn ($tag) => strtolower($tag) === 'php'));
        assertSame(1, $phpCount);
    }
}
