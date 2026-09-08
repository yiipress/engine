<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Content\EntrySorter;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use DateTimeImmutable;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Revs(5)]
#[Iterations(5)]
#[Warmup(1)]
final class EntrySorterBench
{
    /** @var list<Entry> */
    private array $entries;
    private Collection $collection;

    public function setUp(): void
    {
        $this->entries = [];
        for ($i = 0; $i < 10000; ++$i) {
            $date = new DateTimeImmutable('@' . (1700000000 + (($i * 7919) % 1000)));
            $this->entries[] = new Entry(
                filePath: 'post-' . $i . '.md', collection: 'blog', slug: 'post-' . $i, title: 'Post ' . $i,
                date: $date, draft: false, tags: [], categories: [], authors: [], summary: '',
                permalink: '', layout: '', theme: '', weight: 0, language: '', redirectTo: '',
                extra: [], bodyOffset: 0, bodyLength: 0,
            );
        }
        $this->collection = new Collection('blog', 'Blog', '', '/blog/:slug/', 'date', 'desc', 10, true, true);
    }

    public function benchSortDates(): void
    {
        EntrySorter::sort($this->entries, $this->collection);
    }
}
