<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content;

use YiiPress\Content\EntrySorter;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
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

        assertSame(['new', 'mid', 'old'], array_map(static fn(Entry $e) => $e->slug, $sorted));
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

        assertSame(['old', 'mid', 'new'], array_map(static fn(Entry $e) => $e->slug, $sorted));
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

        assertSame(['light', 'medium', 'heavy'], array_map(static fn(Entry $e) => $e->slug, $sorted));
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

        assertSame(['a', 'b', 'c'], array_map(static fn(Entry $e) => $e->slug, $sorted));
    }

    public function testNullDatesAreSortedFirst(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'asc');
        $entries = [
            $this->createEntry(slug: 'dated', date: new DateTimeImmutable('2024-01-01')),
            $this->createEntry(slug: 'undated', date: null),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['undated', 'dated'], array_map(static fn(Entry $e) => $e->slug, $sorted));
    }

    public function testSortByExplicitOrder(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'desc', order: ['second', 'third', 'first']);
        $entries = [
            $this->createEntry(slug: 'first'),
            $this->createEntry(slug: 'second'),
            $this->createEntry(slug: 'third'),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['second', 'third', 'first'], array_map(static fn(Entry $e) => $e->slug, $sorted));
    }

    public function testExplicitOrderOverridesSortBy(): void
    {
        $collection = $this->createCollection(sortBy: 'weight', sortOrder: 'asc', order: ['heavy', 'light']);
        $entries = [
            $this->createEntry(slug: 'light', weight: 1),
            $this->createEntry(slug: 'heavy', weight: 10),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame(['heavy', 'light'], array_map(static fn(Entry $e) => $e->slug, $sorted));
    }

    public function testExplicitOrderUnlistedEntriesGoToEnd(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'desc', order: ['b']);
        $entries = [
            $this->createEntry(slug: 'a'),
            $this->createEntry(slug: 'b'),
            $this->createEntry(slug: 'c'),
        ];

        $sorted = EntrySorter::sort($entries, $collection);

        assertSame('b', $sorted[0]->slug);
    }

    public function testEmptyArrayReturnsEmpty(): void
    {
        $collection = $this->createCollection(sortBy: 'date', sortOrder: 'desc');

        $sorted = EntrySorter::sort([], $collection);

        assertSame([], $sorted);
    }

    public function testEqualKeysKeepInputOrderInBothDirections(): void
    {
        $entries = [
            $this->createEntry(slug: 'z', title: 'Same', date: new DateTimeImmutable('2024-01-01'), weight: 2),
            $this->createEntry(slug: 'a', title: 'Same', date: new DateTimeImmutable('2024-01-01'), weight: 2),
            $this->createEntry(slug: 'm', title: 'Same', date: new DateTimeImmutable('2024-01-01'), weight: 2),
        ];
        foreach (['date', 'weight', 'title', 'unknown'] as $field) {
            foreach (['asc', 'desc'] as $direction) {
                assertSame($entries, EntrySorter::sort($entries, $this->createCollection($field, $direction)));
            }
        }
    }

    public function testDatesPreserveMicrosecondsTimezonesAndStableNullOrdering(): void
    {
        $older = $this->createEntry(slug: 'older', date: new DateTimeImmutable('1960-01-01T00:00:00Z'));
        $first = $this->createEntry(slug: 'z', date: new DateTimeImmutable('2024-01-01T00:00:00.100000Z'));
        $same = $this->createEntry(slug: 'a', date: new DateTimeImmutable('2024-01-01T03:00:00.100000+03:00'));
        $later = $this->createEntry(slug: 'later', date: new DateTimeImmutable('2024-01-01T00:00:00.200000Z'));
        $nullFirst = $this->createEntry(slug: 'null-z');
        $nullSecond = $this->createEntry(slug: 'null-a');
        $entries = [$first, $nullFirst, $later, $same, $older, $nullSecond];

        assertSame([$nullFirst, $nullSecond, $older, $first, $same, $later], EntrySorter::sort($entries, $this->createCollection('date', 'asc')));
        assertSame([$later, $first, $same, $older, $nullFirst, $nullSecond], EntrySorter::sort($entries, $this->createCollection('date', 'desc')));
    }

    public function testNumericTitlesSortAsStrings(): void
    {
        $two = $this->createEntry(slug: 'two', title: '2');
        $ten = $this->createEntry(slug: 'ten', title: '10');
        assertSame([$ten, $two], EntrySorter::sort([$two, $ten], $this->createCollection('title', 'asc')));
        assertSame([$two, $ten], EntrySorter::sort([$ten, $two], $this->createCollection('title', 'desc')));
    }

    public function testExplicitOrderKeepsUnlistedEntriesStableAndUsesLastDuplicatePosition(): void
    {
        $z = $this->createEntry(slug: 'z');
        $a = $this->createEntry(slug: 'a');
        $b = $this->createEntry(slug: 'b');
        $y = $this->createEntry(slug: 'y');
        assertSame([$b, $a, $z, $y], EntrySorter::sort([$z, $a, $b, $y], $this->createCollection('date', 'desc', ['a', 'b', 'a'])));
    }

    /**
     * @param list<string> $order
     */
    private function createCollection(string $sortBy, string $sortOrder, array $order = []): Collection
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
            order: $order,
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
            theme: '',
            weight: $weight,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: $bodyLength,
        );
    }
}
