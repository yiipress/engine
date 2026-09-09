<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\FeedWorkerJob;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeedWorkerJobTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function limits(): iterable
    {
        yield 'limited' => [2, 2];
        yield 'larger than collection' => [20, 3];
        yield 'unlimited zero' => [0, 3];
        yield 'unlimited negative' => [-1, 3];
    }

    #[DataProvider('limits')]
    public function testLimitsEachTaskBeforeSerializationWithoutChangingInput(int $limit, int $expectedCount): void
    {
        $entries = [];
        foreach (['third', 'first', 'second'] as $slug) {
            $entries[] = new Entry(
                filePath: $slug . '.md',
                collection: 'blog',
                slug: $slug,
                title: $slug,
                date: null,
                draft: false,
                tags: [],
                categories: [],
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
                bodyLength: 0,
            );
        }
        $collection = new Collection('blog', 'Blog', '', '/blog/:slug/', 'date', 'desc', 10, true, true, feedLimit: $limit);
        $tasks = [
            ['collectionName' => 'blog', 'collection' => $collection, 'entries' => $entries],
            ['collectionName' => 'empty', 'collection' => $collection, 'entries' => []],
        ];
        $config = new SiteConfig('Site', '', 'https://example.com', 'en', 'UTF-8', '', 'Y-m-d', 10, '/:slug/', [], []);
        $job = new FeedWorkerJob($tasks, $config, '/output', '/content', [], false);

        self::assertSame(array_slice($entries, 0, $expectedCount), $job->tasks()[0]['entries']);
        self::assertSame([], $job->tasks()[1]['entries']);
        self::assertSame($entries, $tasks[0]['entries']);
        self::assertSame($collection, $job->tasks()[0]['collection']);
        self::assertEquals($job, unserialize(serialize($job)));
        self::assertSame($config, $job->siteConfig());
        self::assertSame('/output', $job->outputDir());
        self::assertSame('/content', $job->contentDir());
        self::assertSame([], $job->authors());
        self::assertFalse($job->noWrite());
    }
}
