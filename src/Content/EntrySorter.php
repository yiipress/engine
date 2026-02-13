<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Model\Collection;
use App\Content\Model\Entry;

final class EntrySorter
{
    /**
     * @param list<Entry> $entries
     * @return list<Entry>
     */
    public static function sort(array $entries, Collection $collection): array
    {
        $field = $collection->sortBy;
        $descending = $collection->sortOrder === 'desc';

        usort($entries, static function (Entry $a, Entry $b) use ($field, $descending): int {
            $result = match ($field) {
                'date' => self::compareDates($a, $b),
                'weight' => $a->weight <=> $b->weight,
                'title' => strcmp($a->title, $b->title),
                default => 0,
            };

            return $descending ? -$result : $result;
        });

        return $entries;
    }

    private static function compareDates(Entry $a, Entry $b): int
    {
        if ($a->date === null && $b->date === null) {
            return 0;
        }
        if ($a->date === null) {
            return -1;
        }
        if ($b->date === null) {
            return 1;
        }

        return $a->date <=> $b->date;
    }
}
