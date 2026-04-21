<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Related;

use App\Content\Model\Entry;
use App\Content\Model\RelatedConfig;
use App\Content\Related\RelatedIndex;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;

final class RelatedIndexTest extends TestCase
{
    public function testReturnsEmptyListForUnknownEntry(): void
    {
        $index = new RelatedIndex([]);

        assertSame([], $index->forEntry('/missing.md'));
    }

    public function testOrdersByScoreDescending(): void
    {
        $a = $this->createEntry('/a.md', tags: ['php', 'yii', 'testing']);
        $b = $this->createEntry('/b.md', tags: ['php']);
        $c = $this->createEntry('/c.md', tags: ['php', 'yii']);
        $d = $this->createEntry('/d.md', tags: ['unrelated']);

        $index = new RelatedIndex([
            ['entry' => $a, 'permalink' => '/a/'],
            ['entry' => $b, 'permalink' => '/b/'],
            ['entry' => $c, 'permalink' => '/c/'],
            ['entry' => $d, 'permalink' => '/d/'],
        ]);

        $related = $index->forEntry('/a.md');

        assertCount(2, $related);
        assertSame('/c/', $related[0]->permalink);
        assertSame('/b/', $related[1]->permalink);
    }

    public function testCategoriesWeightedHigherThanTags(): void
    {
        $source = $this->createEntry('/source.md', tags: ['a', 'b'], categories: ['x']);
        $tagMatch = $this->createEntry('/tag.md', tags: ['a', 'b']);
        $categoryMatch = $this->createEntry('/cat.md', categories: ['x']);

        $index = new RelatedIndex(
            [
                ['entry' => $source, 'permalink' => '/source/'],
                ['entry' => $tagMatch, 'permalink' => '/tag/'],
                ['entry' => $categoryMatch, 'permalink' => '/cat/'],
            ],
            new RelatedConfig(tagWeight: 1, categoryWeight: 10),
        );

        $related = $index->forEntry('/source.md');

        assertSame('/cat/', $related[0]->permalink);
    }

    public function testExcludesSelf(): void
    {
        $source = $this->createEntry('/self.md', tags: ['php']);

        $index = new RelatedIndex([
            ['entry' => $source, 'permalink' => '/self/'],
        ]);

        assertSame([], $index->forEntry('/self.md'));
    }

    public function testRespectsLimit(): void
    {
        $entries = [];
        for ($i = 0; $i < 10; $i++) {
            $entry = $this->createEntry("/e$i.md", tags: ['shared']);
            $entries[] = ['entry' => $entry, 'permalink' => "/e$i/"];
        }

        $index = new RelatedIndex($entries, new RelatedConfig(limit: 3));

        assertCount(3, $index->forEntry('/e0.md'));
    }

    public function testSameCollectionOnlyFiltersOutOtherCollections(): void
    {
        $blog = $this->createEntry('/blog/a.md', collection: 'blog', tags: ['shared']);
        $docs = $this->createEntry('/docs/b.md', collection: 'docs', tags: ['shared']);
        $blogSibling = $this->createEntry('/blog/c.md', collection: 'blog', tags: ['shared']);

        $index = new RelatedIndex([
            ['entry' => $blog, 'permalink' => '/blog/a/'],
            ['entry' => $docs, 'permalink' => '/docs/b/'],
            ['entry' => $blogSibling, 'permalink' => '/blog/c/'],
        ]);

        $related = $index->forEntry('/blog/a.md');

        assertCount(1, $related);
        assertSame('/blog/c/', $related[0]->permalink);
    }

    public function testSameCollectionOnlyDisabledAllowsCrossCollection(): void
    {
        $blog = $this->createEntry('/blog/a.md', collection: 'blog', tags: ['shared']);
        $docs = $this->createEntry('/docs/b.md', collection: 'docs', tags: ['shared']);

        $index = new RelatedIndex(
            [
                ['entry' => $blog, 'permalink' => '/blog/a/'],
                ['entry' => $docs, 'permalink' => '/docs/b/'],
            ],
            new RelatedConfig(sameCollectionOnly: false),
        );

        $related = $index->forEntry('/blog/a.md');

        assertCount(1, $related);
        assertSame('/docs/b/', $related[0]->permalink);
    }

    public function testTagMatchingIsCaseInsensitive(): void
    {
        $a = $this->createEntry('/a.md', tags: ['PHP']);
        $b = $this->createEntry('/b.md', tags: ['php']);

        $index = new RelatedIndex([
            ['entry' => $a, 'permalink' => '/a/'],
            ['entry' => $b, 'permalink' => '/b/'],
        ]);

        assertCount(1, $index->forEntry('/a.md'));
    }

    public function testSignatureChangesWithDifferentResults(): void
    {
        $a = $this->createEntry('/a.md', tags: ['shared']);
        $b = $this->createEntry('/b.md', tags: ['shared']);

        $withMatch = new RelatedIndex([
            ['entry' => $a, 'permalink' => '/a/'],
            ['entry' => $b, 'permalink' => '/b/'],
        ]);

        $withoutMatch = new RelatedIndex([
            ['entry' => $a, 'permalink' => '/a/'],
        ]);

        assertNotSame($withMatch->signature(), $withoutMatch->signature());
    }

    /**
     * @param list<string> $tags
     * @param list<string> $categories
     */
    private function createEntry(
        string $filePath,
        string $collection = 'blog',
        array $tags = [],
        array $categories = [],
    ): Entry {
        return new Entry(
            filePath: $filePath,
            collection: $collection,
            slug: basename($filePath, '.md'),
            title: 'Title ' . $filePath,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: $tags,
            categories: $categories,
            authors: [],
            summary: 'Summary',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }
}
