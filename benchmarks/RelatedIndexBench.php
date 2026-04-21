<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Content\Model\Entry;
use App\Content\Model\RelatedConfig;
use App\Content\Related\RelatedIndex;
use DateTimeImmutable;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
final class RelatedIndexBench
{
    /** @var array<int, list<array{entry: Entry, permalink: string}>> */
    private array $datasets = [];

    public function setUp(): void
    {
        foreach ([100, 1000, 5000] as $size) {
            $this->datasets[$size] = $this->generate($size);
        }
    }

    public function provideSizes(): iterable
    {
        yield 'n=100' => ['size' => 100];
        yield 'n=1000' => ['size' => 1000];
        yield 'n=5000' => ['size' => 5000];
    }

    #[Revs(3)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[ParamProviders('provideSizes')]
    public function benchBuildIndex(array $params): void
    {
        new RelatedIndex($this->datasets[$params['size']], new RelatedConfig());
    }

    /**
     * @return list<array{entry: Entry, permalink: string}>
     */
    private function generate(int $size): array
    {
        $tagPool = ['php', 'yii', 'performance', 'testing', 'markdown', 'rust', 'ffi', 'build', 'static-sites', 'plugins'];
        $categoryPool = ['tutorial', 'news', 'reference', 'showcase'];
        $entries = [];

        for ($i = 0; $i < $size; $i++) {
            $tags = [];
            for ($t = 0; $t < 3; $t++) {
                $tags[] = $tagPool[($i * 7 + $t * 3) % count($tagPool)];
            }
            $categories = [$categoryPool[$i % count($categoryPool)]];

            $entry = new Entry(
                filePath: "/bench/entry-$i.md",
                collection: 'blog',
                slug: "entry-$i",
                title: "Entry $i",
                date: new DateTimeImmutable('2024-01-01'),
                draft: false,
                tags: array_values(array_unique($tags)),
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

            $entries[] = ['entry' => $entry, 'permalink' => "/blog/entry-$i/"];
        }

        return $entries;
    }
}
