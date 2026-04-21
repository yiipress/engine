<?php

declare(strict_types=1);

namespace App\Content\Related;

use App\Content\Model\Entry;
use App\Content\Model\RelatedConfig;
use App\Content\Model\RelatedEntry;

use function array_slice;
use function arsort;
use function count;
use function hash;
use function mb_strtolower;

/**
 * Pre-computed map of source entry file path to a ranked list of related entries.
 *
 * Similarity is scored by the number of shared tags and categories, weighted by
 * {@see RelatedConfig}. An inverted index (term → entry indices) keeps the
 * computation at O(sum of term postings) rather than O(N²).
 */
final class RelatedIndex
{
    /** @var array<string, list<RelatedEntry>> */
    private array $byFilePath = [];

    private string $signature = '';

    /**
     * @param list<array{entry: Entry, permalink: string}> $entries
     */
    public function __construct(array $entries, RelatedConfig $config = new RelatedConfig())
    {
        $this->build($entries, $config);
    }

    /**
     * @return list<RelatedEntry>
     */
    public function forEntry(string $filePath): array
    {
        return $this->byFilePath[$filePath] ?? [];
    }

    /**
     * Stable hash of the computed index contents, useful as a cache invalidation key.
     */
    public function signature(): string
    {
        return $this->signature;
    }

    /**
     * @param list<array{entry: Entry, permalink: string}> $entries
     */
    private function build(array $entries, RelatedConfig $config): void
    {
        $total = count($entries);
        if ($total === 0) {
            return;
        }

        $tagIndex = [];
        $categoryIndex = [];
        foreach ($entries as $i => $task) {
            $entry = $task['entry'];
            foreach ($entry->tags as $tag) {
                $tagIndex[mb_strtolower($tag)][] = $i;
            }
            foreach ($entry->categories as $category) {
                $categoryIndex[mb_strtolower($category)][] = $i;
            }
        }

        $signatureParts = [];
        foreach ($entries as $i => $task) {
            $source = $task['entry'];
            $scores = [];

            $this->accumulateScores($scores, $source->tags, $tagIndex, $i, $config->tagWeight);
            $this->accumulateScores($scores, $source->categories, $categoryIndex, $i, $config->categoryWeight);

            if ($scores === []) {
                continue;
            }

            if ($config->sameCollectionOnly) {
                foreach ($scores as $j => $_) {
                    if ($entries[$j]['entry']->collection !== $source->collection) {
                        unset($scores[$j]);
                    }
                }
                if ($scores === []) {
                    continue;
                }
            }

            arsort($scores);
            $top = array_slice($scores, 0, $config->limit, true);

            $related = [];
            foreach ($top as $j => $score) {
                $target = $entries[$j]['entry'];
                $related[] = new RelatedEntry(
                    title: $target->title,
                    permalink: $entries[$j]['permalink'],
                    date: $target->date,
                    summary: $target->summary(),
                    score: $score,
                );
                $signatureParts[] = $source->filePath . '|' . $entries[$j]['permalink'] . '|' . $score;
            }

            $this->byFilePath[$source->filePath] = $related;
        }

        $this->signature = hash('xxh128', implode("\n", $signatureParts));
    }

    /**
     * @param array<int, int> $scores
     * @param list<string> $terms
     * @param array<string, list<int>> $index
     */
    private function accumulateScores(array &$scores, array $terms, array $index, int $selfIndex, int $weight): void
    {
        if ($weight <= 0) {
            return;
        }
        foreach ($terms as $term) {
            $key = mb_strtolower($term);
            if (!isset($index[$key])) {
                continue;
            }
            foreach ($index[$key] as $j) {
                if ($j === $selfIndex) {
                    continue;
                }
                $scores[$j] = ($scores[$j] ?? 0) + $weight;
            }
        }
    }
}
