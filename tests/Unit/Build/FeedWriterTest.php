<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\FeedWriter;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeedWriterTest extends TestCase
{
    /** @return iterable<string, array{list<array{int, int}>, int, int}> */
    public static function workloads(): iterable
    {
        yield 'no feeds' => [[], 4, 1];
        yield 'limited large collections' => [[[5000, 20], [5000, 20]], 4, 1];
        yield 'limit exceeds entries' => [[[20, 5000], [20, 5000]], 4, 1];
        yield 'below threshold' => [[[500, 0], [499, -1]], 4, 1];
        yield 'threshold' => [[[500, 0], [500, -1]], 4, 2];
        yield 'limited feeds at threshold' => [[[5000, 500], [5000, 500]], 4, 2];
        yield 'one large collection' => [[[5000, 0]], 4, 1];
        yield 'requested worker cap' => [[[1000, 0], [1000, 0], [1000, 0]], 2, 2];
        yield 'explicit sequential' => [[[1000, 0], [1000, 0]], 1, 1];
    }

    /** @param list<array{int, int}> $workloads */
    #[DataProvider('workloads')]
    public function testSelectsWorkersFromFeedEntryVolume(array $workloads, int $requested, int $expected): void
    {
        $entry = new Entry(
            filePath: '', collection: 'blog', slug: 'post', title: 'Post', date: null, draft: false,
            tags: [], categories: [], authors: [], summary: '', permalink: '', layout: '', theme: '',
            weight: 0, language: '', redirectTo: '', extra: [], bodyOffset: 0, bodyLength: 0,
        );
        $tasks = [];
        foreach ($workloads as [$count, $limit]) {
            $collection = new Collection('blog', 'Blog', '', '/blog/:slug/', 'date', 'desc', 10, true, true, feedLimit: $limit);
            $tasks[] = ['collectionName' => 'blog', 'collection' => $collection, 'entries' => array_fill(0, $count, $entry)];
        }

        $writer = new FeedWriter(new ContentProcessorPipeline(), []);
        self::assertSame(function_exists('proc_open') ? $expected : 1, $writer->workerCountFor($tasks, $requested));
    }
}
