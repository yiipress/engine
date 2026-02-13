<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Model\Collection;
use App\Content\Model\Entry;

final class PermalinkResolver
{
    public static function resolve(Entry $entry, Collection $collection): string
    {
        if ($entry->permalink !== '') {
            return $entry->permalink;
        }

        $pattern = $collection->permalink;

        $replacements = [
            ':collection' => $collection->name,
            ':slug' => $entry->slug,
        ];

        if ($entry->date !== null) {
            $replacements[':year'] = $entry->date->format('Y');
            $replacements[':month'] = $entry->date->format('m');
            $replacements[':day'] = $entry->date->format('d');
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $pattern,
        );
    }
}
