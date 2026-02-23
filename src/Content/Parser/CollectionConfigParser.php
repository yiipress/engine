<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Collection;
use RuntimeException;

use function file_get_contents;
use function is_array;
use function yaml_parse;

final class CollectionConfigParser
{
    public function parse(string $filePath, string $collectionName): Collection
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: $filePath");
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new RuntimeException("Invalid YAML in file: $filePath");
        }

        return new Collection(
            name: $collectionName,
            title: (string) ($data['title'] ?? $collectionName),
            description: (string) ($data['description'] ?? ''),
            permalink: (string) ($data['permalink'] ?? '/:collection/:slug/'),
            sortBy: (string) ($data['sort_by'] ?? 'date'),
            sortOrder: (string) ($data['sort_order'] ?? 'desc'),
            entriesPerPage: (int) ($data['entries_per_page'] ?? 10),
            feed: (bool) ($data['feed'] ?? false),
            listing: (bool) ($data['listing'] ?? true),
            order: isset($data['order']) && is_array($data['order'])
                ? array_values(array_map(strval(...), $data['order']))
                : [],
        );
    }
}
