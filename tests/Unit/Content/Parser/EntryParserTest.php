<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content\Parser;

use YiiPress\Content\Parser\EntryParser;
use YiiPress\Content\Parser\FilenameParser;
use YiiPress\Content\Parser\FrontMatterParser;
use YiiPress\Content\Parser\InvalidContentConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

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

    public function testParseEntryAliases(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-aliases-') . '.md';
        file_put_contents($file, "---\ntitle: Aliased\naliases:\n  - /old-url/\n  - legacy-url\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'blog');

            assertSame(['/old-url/', 'legacy-url'], $entry->aliases);
        } finally {
            unlink($file);
        }
    }

    public function testParsesPagerOverrides(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-pager-') . '.md';
        file_put_contents(
            $file,
            "---\ntitle: Pager\nprevious: false\nnext:\n  text: Continue\n  link: /guide/continue/\n---\n\nBody.\n",
        );

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertFalse($entry->previous);
            assertSame(['text' => 'Continue', 'link' => '/guide/continue/'], $entry->next);
        } finally {
            unlink($file);
        }
    }

    public function testParsesEditLinkVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-edit-link-');
        file_put_contents($file, "---\ntitle: Generated\nedit_link: false\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertFalse($entry->editLink);
        } finally {
            unlink($file);
        }
    }

    public function testEditLinkVisibilityIsInheritedWhenOmitted(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-edit-link-');
        file_put_contents($file, "---\ntitle: Editable\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertNull($entry->editLink);
        } finally {
            unlink($file);
        }
    }

    public function testParsesAsideVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-aside-');
        file_put_contents($file, "---\ntitle: Focused\naside: false\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');
            assertFalse($entry->aside);
        } finally {
            unlink($file);
        }
    }

    public function testParsesEnabledAsideVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-aside-');
        file_put_contents($file, "---\ntitle: Full layout\naside: true\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');
            assertTrue($entry->aside);
        } finally {
            unlink($file);
        }
    }

    public function testAsideVisibilityIsInheritedWhenOmitted(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-aside-');
        file_put_contents($file, "---\ntitle: Default\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');
            assertNull($entry->aside);
        } finally {
            unlink($file);
        }
    }

    public function testRejectsInvalidAsideVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-aside-invalid-');
        file_put_contents($file, "---\ntitle: Invalid\naside: right\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid aside visibility override to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid "aside" visibility override', $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    #[DataProvider('faqLevelProvider')]
    public function testParsesFaqLevel(string $yaml, int|false|null $expected): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-faq-');
        file_put_contents($file, "---\ntitle: FAQ\n$yaml\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertSame($expected, $entry->faqLevel);
        } finally {
            unlink($file);
        }
    }

    public static function faqLevelProvider(): iterable
    {
        yield 'inline' => ['faq_level: false', false];
        yield 'page end' => ['faq_level: 0', 0];
        yield 'heading level' => ['faq_level: 3', 3];
        yield 'null inheritance' => ['faq_level: null', null];
        yield 'omitted inheritance' => ['', null];
    }

    #[DataProvider('invalidFaqLevelProvider')]
    public function testRejectsInvalidFaqLevel(string $yaml): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-faq-invalid-');
        file_put_contents($file, "---\ntitle: Invalid\n$yaml\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid FAQ grouping level to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid "faq_level" grouping mode', $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    public static function invalidFaqLevelProvider(): iterable
    {
        yield 'true' => ['faq_level: true'];
        yield 'negative' => ['faq_level: -1'];
        yield 'above maximum' => ['faq_level: 7'];
        yield 'string' => ['faq_level: section'];
        yield 'array' => ['faq_level: [2]'];
    }

    #[DataProvider('tocOverrideProvider')]
    public function testParsesTocOverride(string $yaml, array|false|null $expected): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-toc-');
        file_put_contents($file, "---\ntitle: Outline\n$yaml\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertSame($expected, $entry->toc);
        } finally {
            unlink($file);
        }
    }

    public static function tocOverrideProvider(): iterable
    {
        yield 'disabled' => ['toc: false', false];
        yield 'single level' => ['toc: 3', [3, 3]];
        yield 'inclusive range' => ["toc:\n  - 2\n  - 4", [2, 4]];
        yield 'deep' => ['toc: deep', [2, 6]];
        yield 'null inheritance' => ['toc: null', null];
        yield 'omitted inheritance' => ['', null];
    }

    #[DataProvider('invalidTocOverrideProvider')]
    public function testRejectsInvalidTocOverride(string $yaml): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-toc-invalid-');
        file_put_contents($file, "---\ntitle: Invalid\n$yaml\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid table-of-contents override to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid "toc" heading range', $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    public static function invalidTocOverrideProvider(): iterable
    {
        yield 'true' => ['toc: true'];
        yield 'zero' => ['toc: 0'];
        yield 'above maximum' => ['toc: 7'];
        yield 'reversed range' => ['toc: [4, 2]'];
        yield 'range below minimum' => ['toc: [0, 2]'];
        yield 'range above maximum' => ['toc: [2, 7]'];
        yield 'range wrong length' => ['toc: [2, 3, 4]'];
        yield 'non-integer range' => ['toc: [2, deep]'];
        yield 'unknown string' => ['toc: shallow'];
    }

    public function testLegacyEditLinkVisibilityRemainsSupported(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-edit-link-legacy-');
        file_put_contents($file, "---\ntitle: Generated\neditLink: false\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertFalse($entry->editLink);
        } finally {
            unlink($file);
        }
    }

    public function testSnakeCaseEditLinkTakesPrecedenceOverLegacyKey(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-edit-link-precedence-');
        file_put_contents($file, "---\ntitle: Generated\neditLink: false\nedit_link: true\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertTrue($entry->editLink);
        } finally {
            unlink($file);
        }
    }

    public function testParsesLastUpdatedVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-last-updated-');
        file_put_contents($file, "---\ntitle: Generated\nlast_updated: false\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertFalse($entry->lastUpdated);
        } finally {
            unlink($file);
        }
    }

    public function testParsesEnabledLastUpdatedVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-last-updated-');
        file_put_contents($file, "---\ntitle: Generated\nlast_updated: true\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertTrue($entry->lastUpdated);
        } finally {
            unlink($file);
        }
    }

    public function testLastUpdatedVisibilityIsInheritedWhenNull(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-last-updated-');
        file_put_contents($file, "---\ntitle: Generated\nlast_updated: null\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertNull($entry->lastUpdated);
        } finally {
            unlink($file);
        }
    }

    public function testLastUpdatedVisibilityIsInheritedWhenOmitted(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-last-updated-');
        file_put_contents($file, "---\ntitle: Generated\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($file, 'docs');

            assertNull($entry->lastUpdated);
        } finally {
            unlink($file);
        }
    }

    public function testRejectsInvalidLastUpdatedVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-last-updated-invalid-');
        file_put_contents($file, "---\ntitle: Generated\nlast_updated: yesterday\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid last-updated visibility override to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid "last_updated" visibility override', $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    public function testRejectsInvalidEditLinkVisibilityOverride(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-edit-link-invalid-');
        file_put_contents($file, "---\ntitle: Editable\nedit_link: hidden\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid edit-link visibility override to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid "edit_link" visibility override', $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    #[DataProvider('invalidPagerOverrideProvider')]
    public function testRejectsInvalidPagerOverrides(string $yaml, string $message): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-entry-pager-invalid-') . '.md';
        file_put_contents($file, "---\ntitle: Pager\n$yaml\n---\n\nBody.\n");

        try {
            $this->parser->parse($file, 'docs');
            self::fail('Expected invalid pager override to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString($message, $e->getMessage());
            assertSame($file, $e->filePath());
        } finally {
            unlink($file);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPagerOverrideProvider(): iterable
    {
        yield 'scalar' => ['previous: Previous page', 'Invalid "previous" pager override'];
        yield 'missing text' => ["next:\n  link: /next/", 'Invalid "next" pager override'];
        yield 'extra field' => ["next:\n  text: Next\n  link: /next/\n  target: _blank", 'Invalid "next" pager override'];
        yield 'unsafe scheme' => ["previous:\n  text: Bad\n  link: 'javascript:alert(1)'", 'Unsafe URL'];
        yield 'protocol relative' => ["next:\n  text: Bad\n  link: //evil.example/page", 'Unsafe URL'];
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

        // Should have front matter tag 'php' plus inline tags 'inline', 'shit', 'yii', 'multi-part-tag', 'two-words'
        // 'php' should not be duplicated (it's in both front matter and body)
        // Inline tags are normalized to lowercase
        assertSame(['php', 'inline', 'shit', 'yii', 'multi-part-tag', 'two-words'], $entry->tags);
    }

    public function testHyphenatedInlineTagsAreExtracted(): void
    {
        $entry = $this->parser->parse($this->dataDir . '/blog/2026-03-30-inline-tags.md', 'blog');

        $tagLowercases = array_map(strtolower(...), $entry->tags);
        assertSame(true, in_array('multi-part-tag', $tagLowercases, true));
        assertSame(true, in_array('two-words', $tagLowercases, true));
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
        assertSame(['php', 'inline', 'shit', 'yii', 'multi-part-tag', 'two-words'], $tagLowercases);

        // Verify 'php' appears only once (not duplicated as 'php' and 'PHP')
        $phpCount = count(array_filter($entry->tags, static fn($tag) => strtolower($tag) === 'php'));
        assertSame(1, $phpCount);
    }

    public function testParseEntryWithoutTitleReturnsMinimalEntry(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'yiipress-entry-');
        self::assertNotFalse($filePath);

        file_put_contents($filePath, "---\n---\ntags:\n  - php\n\n#not-a-heading\n");

        try {
            $entry = $this->parser->parse($filePath, 'blog');

            assertSame('', $entry->title);
            assertSame('', $entry->slug);
            assertNull($entry->date);
            assertSame([], $entry->tags);
            assertSame([], $entry->categories);
            assertSame([], $entry->authors);
        } finally {
            unlink($filePath);
        }
    }

    public function testInvalidFrontMatterDateThrowsFriendlyExceptionWithFilePath(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'yiipress-entry-');
        self::assertNotFalse($filePath);

        file_put_contents($filePath, "---\ntitle: Bad Date\ndate: not-a-real-date\n---\n\nBody.\n");

        try {
            $this->parser->parse($filePath, 'blog');
            self::fail('Expected invalid date to throw.');
        } catch (InvalidContentConfigException $e) {
            assertStringContainsString('Invalid date in front matter', $e->getMessage());
            assertSame($filePath, $e->filePath());
        } finally {
            unlink($filePath);
        }
    }

    public function testParsesTopLevelShowTitle(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'yiipress-entry-');
        self::assertNotFalse($filePath);

        file_put_contents($filePath, "---\ntitle: Logo First\nshow_title: false\n---\n\nBody.\n");

        try {
            $entry = $this->parser->parse($filePath, 'pages');

            assertFalse($entry->showTitle);
            assertSame([], $entry->extra);
        } finally {
            unlink($filePath);
        }
    }

    public function testLegacyShowTitleRemainsSupportedWithSnakeCasePrecedence(): void
    {
        $legacyFile = tempnam(sys_get_temp_dir(), 'yiipress-entry-show-title-legacy-');
        $precedenceFile = tempnam(sys_get_temp_dir(), 'yiipress-entry-show-title-precedence-');
        file_put_contents($legacyFile, "---\ntitle: Legacy\nshowTitle: false\n---\n\nBody.\n");
        file_put_contents(
            $precedenceFile,
            "---\ntitle: Precedence\nshowTitle: false\nshow_title: true\n---\n\nBody.\n",
        );

        try {
            assertFalse($this->parser->parse($legacyFile, 'docs')->showTitle);
            assertTrue($this->parser->parse($precedenceFile, 'docs')->showTitle);
        } finally {
            unlink($legacyFile);
            unlink($precedenceFile);
        }
    }
}
