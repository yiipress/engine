<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\Model\Entry;
use App\Content\TaxonomyCollector;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

final class TaxonomyCollectorTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/yiipress-taxonomy-test-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "body\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testCollectGroupsEntriesByTags(): void
    {
        $entries = [
            $this->createEntry(slug: 'a', tags: ['php', 'yii']),
            $this->createEntry(slug: 'b', tags: ['php']),
            $this->createEntry(slug: 'c', tags: ['javascript']),
        ];

        $result = TaxonomyCollector::collect(['tags'], $entries);

        assertArrayHasKey('tags', $result);
        assertCount(3, $result['tags']);
        assertCount(2, $result['tags']['php']);
        assertCount(1, $result['tags']['yii']);
        assertCount(1, $result['tags']['javascript']);
    }

    public function testCollectGroupsEntriesByCategories(): void
    {
        $entries = [
            $this->createEntry(slug: 'a', categories: ['tutorials']),
            $this->createEntry(slug: 'b', categories: ['tutorials', 'news']),
        ];

        $result = TaxonomyCollector::collect(['categories'], $entries);

        assertArrayHasKey('categories', $result);
        assertCount(2, $result['categories']);
        assertCount(2, $result['categories']['tutorials']);
        assertCount(1, $result['categories']['news']);
    }

    public function testCollectMultipleTaxonomies(): void
    {
        $entries = [
            $this->createEntry(slug: 'a', tags: ['php'], categories: ['tutorials']),
        ];

        $result = TaxonomyCollector::collect(['tags', 'categories'], $entries);

        assertArrayHasKey('tags', $result);
        assertArrayHasKey('categories', $result);
        assertCount(1, $result['tags']['php']);
        assertCount(1, $result['categories']['tutorials']);
    }

    public function testCollectReturnsEmptyForNoEntries(): void
    {
        $result = TaxonomyCollector::collect(['tags'], []);

        assertArrayHasKey('tags', $result);
        assertSame([], $result['tags']);
    }

    public function testTermsAreSortedAlphabetically(): void
    {
        $entries = [
            $this->createEntry(slug: 'a', tags: ['zeta', 'alpha', 'mu']),
        ];

        $result = TaxonomyCollector::collect(['tags'], $entries);

        assertSame(['alpha', 'mu', 'zeta'], array_keys($result['tags']));
    }

    /**
     * @param list<string> $tags
     * @param list<string> $categories
     */
    private function createEntry(
        string $slug,
        array $tags = [],
        array $categories = [],
    ): Entry {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: $slug,
            title: ucfirst($slug),
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: $tags,
            categories: $categories,
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: (int) filesize($this->tempFile),
        );
    }
}
