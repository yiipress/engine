<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\I18n;

use App\Content\I18n\TranslationIndex;
use App\Content\Model\Entry;
use App\Content\Model\I18nConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;

final class TranslationIndexTest extends TestCase
{
    public function testGroupsEntriesSharingExplicitTranslationKey(): void
    {
        $en = $this->createEntry('/en/post.md', language: 'en', translationKey: 'hello');
        $ru = $this->createEntry('/ru/post.md', language: 'ru', translationKey: 'hello', title: 'Привет');

        $index = new TranslationIndex(
            [
                ['entry' => $en, 'permalink' => '/hello/'],
                ['entry' => $ru, 'permalink' => '/ru/hello/'],
            ],
            new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
        );

        $alternates = $index->forEntry('/en/post.md');

        assertCount(1, $alternates);
        assertSame('ru', $alternates[0]->language);
        assertSame('/ru/hello/', $alternates[0]->permalink);
        assertSame('Привет', $alternates[0]->title);
    }

    public function testFallsBackToSlugWhenTranslationKeyNotSet(): void
    {
        $en = $this->createEntry('/en.md', language: 'en', slug: 'hello');
        $de = $this->createEntry('/de.md', language: 'de', slug: 'hello');

        $index = new TranslationIndex(
            [
                ['entry' => $en, 'permalink' => '/hello/'],
                ['entry' => $de, 'permalink' => '/de/hello/'],
            ],
            new I18nConfig(languages: ['en', 'de'], defaultLanguage: 'en'),
        );

        assertCount(1, $index->forEntry('/en.md'));
        assertCount(1, $index->forEntry('/de.md'));
    }

    public function testDoesNotLinkAcrossCollections(): void
    {
        $blog = $this->createEntry('/blog.md', collection: 'blog', language: 'en', slug: 'hello');
        $docs = $this->createEntry('/docs.md', collection: 'docs', language: 'ru', slug: 'hello');

        $index = new TranslationIndex(
            [
                ['entry' => $blog, 'permalink' => '/blog/hello/'],
                ['entry' => $docs, 'permalink' => '/ru/docs/hello/'],
            ],
            new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
        );

        assertSame([], $index->forEntry('/blog.md'));
    }

    public function testEmptyLanguageTreatedAsDefault(): void
    {
        $en = $this->createEntry('/en.md', language: '', slug: 'hello');
        $ru = $this->createEntry('/ru.md', language: 'ru', slug: 'hello');

        $index = new TranslationIndex(
            [
                ['entry' => $en, 'permalink' => '/hello/'],
                ['entry' => $ru, 'permalink' => '/ru/hello/'],
            ],
            new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
        );

        $alternates = $index->forEntry('/en.md');

        assertCount(1, $alternates);
        assertSame('ru', $alternates[0]->language);
    }

    public function testSkipsUnknownLanguages(): void
    {
        $en = $this->createEntry('/en.md', language: 'en', slug: 'hello');
        $zh = $this->createEntry('/zh.md', language: 'zh', slug: 'hello');

        $index = new TranslationIndex(
            [
                ['entry' => $en, 'permalink' => '/hello/'],
                ['entry' => $zh, 'permalink' => '/zh/hello/'],
            ],
            new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
        );

        assertSame([], $index->forEntry('/en.md'));
    }

    public function testSoloEntryHasNoAlternates(): void
    {
        $en = $this->createEntry('/en.md', language: 'en', slug: 'hello');

        $index = new TranslationIndex(
            [['entry' => $en, 'permalink' => '/hello/']],
            new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en'),
        );

        assertSame([], $index->forEntry('/en.md'));
    }

    public function testSignatureDiffersBetweenDistinctIndices(): void
    {
        $en = $this->createEntry('/en.md', language: 'en', slug: 'hello');
        $ru = $this->createEntry('/ru.md', language: 'ru', slug: 'hello');
        $config = new I18nConfig(languages: ['en', 'ru'], defaultLanguage: 'en');

        $paired = new TranslationIndex(
            [
                ['entry' => $en, 'permalink' => '/hello/'],
                ['entry' => $ru, 'permalink' => '/ru/hello/'],
            ],
            $config,
        );
        $alone = new TranslationIndex(
            [['entry' => $en, 'permalink' => '/hello/']],
            $config,
        );

        assertNotSame($paired->signature(), $alone->signature());
    }

    private function createEntry(
        string $filePath,
        string $collection = 'blog',
        string $language = '',
        string $slug = 'post',
        string $translationKey = '',
        string $title = 'Post',
    ): Entry {
        return new Entry(
            filePath: $filePath,
            collection: $collection,
            slug: $slug,
            title: $title,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: $language,
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
            translationKey: $translationKey,
        );
    }
}
