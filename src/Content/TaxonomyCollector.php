<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Model\Entry;

final class TaxonomyCollector
{
    /**
     * @param list<string> $taxonomyNames
     * @param list<Entry> $entries
     * @return array<string, array<string, list<Entry>>>
     */
    public static function collect(array $taxonomyNames, array $entries): array
    {
        $result = [];
        foreach ($taxonomyNames as $taxonomy) {
            $result[$taxonomy] = [];
        }

        foreach ($entries as $entry) {
            foreach ($taxonomyNames as $taxonomy) {
                $terms = self::getTerms($entry, $taxonomy);
                foreach ($terms as $term) {
                    $result[$taxonomy][$term][] = $entry;
                }
            }
        }

        foreach ($result as $taxonomy => $terms) {
            ksort($result[$taxonomy]);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function getTerms(Entry $entry, string $taxonomy): array
    {
        return match ($taxonomy) {
            'tags' => $entry->tags,
            'categories' => $entry->categories,
            default => [],
        };
    }
}
