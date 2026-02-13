<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\EntrySorter;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class EntrySorterTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/yiipress-sorter-test-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "body\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testSortByDateDescending(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'desc');
        $entries = [
            $this->createEntry(slug: 'old', date: new DateTimeImmutable('2024-01-01')),
            $this->createEntry(slug: 'new', date: new DateTimeImmutable('2024-03-01')),
            $this->createEntry(slug: 'mid', date: new DateTimeImmutable('2024-02-01')),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['new', 'mid', 'old'], array_map(static fn (Entry $e) => $e->slug, $sorted));
    }

    public function testSortByDateAscending(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'asc');
        $entries = [
            $this->createEntry(slug: 'new', date: new DateTimeImmutable('2024-03-01')),
            $this->createEntry(slug: 'old', date: new DateTimeImmutable('2024-01-01')),
            $this->createEntry(slug: 'mid', date: new DateTimeImmutable('2024-02-01')),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['old', 'mid', 'new'], array_map(static fn (Entry $e) => $e->slug, $sorted));
    }

    public function testSortByWeight(): void
    {
        $collection = $this->createCollection(sortBy: 'weight', sortOrder: 'asc');
        $entries = [
            $this->createEntry(slug: 'heavy', weight: 10),
            $this->createEntry(slug: 'light', weight: 1),
            $this->createEntry(slug: 'medium', weight: 5),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['light', 'medium', 'heavy'], array_map(static fn (Entry $e) => $e->slug, $sorted));
    }

    public function testSortByTitle(): void
    {
        $collection = $this->createCollection(sortBy: 'title', sortOrder: 'asc');
        $entries = [
            $this->createEntry(slug: 'c', title: 'Charlie'),
            $this->createEntry(slug: 'a', title: 'Alpha'),
            $this->createEntry(slug: 'b', title: 'Bravo'),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['a', 'b', 'c'], array_map(static fn (Entry $e) => $e->slug, $sorted));
    }

    public function testNullDatesAreSortedFirst(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'asc');
        $entries = [
            $this->createEntry(slug: 'dated', date: new DateTimeImmutable('2024-01-01')),
            $this->createEntry(slug: 'undated', date: null),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['undated', 'dated'], array_map(static fn (Entry $e) => $e->slug, $sorted));
    }

    public function testEmptyArrayReturnsEmpty(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'desc');

        $sorted = EntrySorter::sort([], $collection);

        assertSame([], $sorted);
    }

    private function createCollection(string $sortBy, string $sortOrder): Collection
    {
        return new Collection(
            name: 'test',
            title: 'Test',
            description: '',
            permalink: '/test/:slug/',
            sortBy: $sortBy,
            sortOrder: $sortOrder,
            entriesPerPage: 10,
            feed: false,
            listing: true,
        );
    }

    private function createEntry(
        string $slug = 'test',
        string $title = 'Test',
        ?DateTimeImmutable $date = null,
        int $weight = 0,
    ): Entry {
        $bodyLength = (int) filesize($this->tempFile);

        return new Entry(
            filePath: $this->tempFile,
            collection: 'test',
            slug: $slug,
            title: $title,
            date: $date,
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            weight: $weight,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: $bodyLength,
        );
    }
}
