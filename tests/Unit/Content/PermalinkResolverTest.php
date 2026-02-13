<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\PermalinkResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class PermalinkResolverTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/yiipress-permalink-test-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "body\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testEntryPermalinkOverridesCollectionPattern(): void
    {
        $collection = $this->createCollection('/blog/:slug/');
        $entry = $this->createEntry(permalink: '/custom/path/');

        assertSame('/custom/path/', PermalinkResolver::resolve($entry, $collection));
    }

    public function testCollectionPatternWithSlugPlaceholder(): void
    {
        $collection = $this->createCollection('/blog/:slug/');
        $entry = $this->createEntry(slug: 'hello-world');

        assertSame('/blog/hello-world/', PermalinkResolver::resolve($entry, $collection));
    }

    public function testCollectionPatternWithCollectionPlaceholder(): void
    {
        $collection = $this->createCollection('/:collection/:slug/');
        $entry = $this->createEntry(slug: 'my-post');

        assertSame('/blog/my-post/', PermalinkResolver::resolve($entry, $collection));
    }

    public function testDatePlaceholders(): void
    {
        $collection = $this->createCollection('/:collection/:year/:month/:day/:slug/');
        $entry = $this->createEntry(
            slug: 'my-post',
            date: new DateTimeImmutable('2024-03-15'),
        );

        assertSame('/blog/2024/03/15/my-post/', PermalinkResolver::resolve($entry, $collection));
    }

    public function testDatePlaceholdersWithoutDate(): void
    {
        $collection = $this->createCollection('/:collection/:year/:month/:slug/');
        $entry = $this->createEntry(slug: 'undated', date: null);

        assertSame('/blog/:year/:month/undated/', PermalinkResolver::resolve($entry, $collection));
    }

    public function testYearMonthPattern(): void
    {
        $collection = $this->createCollection('/:year/:month/:slug.html');
        $entry = $this->createEntry(
            slug: 'post',
            date: new DateTimeImmutable('2025-12-01'),
        );

        assertSame('/2025/12/post.html', PermalinkResolver::resolve($entry, $collection));
    }

    private function createCollection(string $permalink): Collection
    {
        return new Collection(
            name: 'blog',
            title: 'Blog',
            description: '',
            permalink: $permalink,
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );
    }

    private function createEntry(
        string $slug = 'test',
        string $permalink = '',
        ?DateTimeImmutable $date = null,
    ): Entry {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: $slug,
            title: 'Test',
            date: $date,
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: $permalink,
            layout: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: (int) filesize($this->tempFile),
        );
    }
}
