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
            'tags' => self::allTags($entry),
            'categories' => $entry->categories,
            default => [],
        };
    }

    /**
     * Merges frontmatter tags with inline #tags found in the entry body.
     *
     * @return list<string>
     */
    private static function allTags(Entry $entry): array
    {
        $body = $entry->body();
        if ($body === '') {
            return $entry->tags;
        }

        preg_match_all('/#(\w+)/u', $body, $matches);
        if ($matches[1] === []) {
            return $entry->tags;
        }

        $inlineTags = array_map(mb_strtolower(...), $matches[1]);
        $frontmatterLower = array_map(mb_strtolower(...), $entry->tags);

        $merged = $entry->tags;
        foreach ($inlineTags as $tag) {
            if (!in_array($tag, $frontmatterLower, true)) {
                $merged[] = $tag;
            }
        }

        return array_values($merged);
    }
}
