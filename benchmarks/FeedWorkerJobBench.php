<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\FeedWorkerJob;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Revs(5)]
#[Iterations(5)]
#[Warmup(1)]
final class FeedWorkerJobBench
{
    /** @var list<array{collectionName: string, collection: Collection, entries: list<Entry>}> */
    private array $tasks;
    private SiteConfig $config;

    public function setUp(): void
    {
        $entries = [];
        for ($i = 0; $i < 10000; ++$i) {
            $entries[] = new Entry(
                filePath: 'post-' . $i . '.md', collection: 'blog', slug: 'post-' . $i, title: 'Post ' . $i,
                date: null, draft: false, tags: [], categories: [], authors: [], summary: '',
                permalink: '', layout: '', theme: '', weight: 0, language: '', redirectTo: '',
                extra: [], bodyOffset: 0, bodyLength: 0,
            );
        }
        $collection = new Collection('blog', 'Blog', '', '/blog/:slug/', 'date', 'desc', 10, true, true);
        $this->tasks = [['collectionName' => 'blog', 'collection' => $collection, 'entries' => $entries]];
        $this->config = new SiteConfig('Site', '', 'https://example.com', 'en', 'UTF-8', '', 'Y-m-d', 10, '/:slug/', [], []);
    }

    public function benchSerializeLimitedFeed(): void
    {
        serialize(new FeedWorkerJob($this->tasks, $this->config, '/output', '/content', [], false));
    }
}
