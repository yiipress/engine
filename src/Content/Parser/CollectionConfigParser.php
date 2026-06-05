<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Collection;

use function array_is_list;
use function file_get_contents;
use function implode;
use function is_array;
use function yaml_parse;

final class CollectionConfigParser
{
    public function parse(string $filePath, string $collectionName): Collection
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidContentConfigException(
                "Cannot read collection configuration file: $filePath",
                $filePath,
                'Check that the file exists and is readable by the build process.',
            );
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new InvalidContentConfigException(
                "Invalid YAML in collection configuration file: $filePath",
                $filePath,
                "Fix the YAML syntax in $filePath, then run the build again.",
            );
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidContentConfigException(
                'The collection configuration file must contain YAML key-value pairs.',
                $filePath,
                implode("\n", [
                    'Use mappings such as:',
                    'title: Blog',
                    'permalink: /blog/:slug/',
                ]),
            );
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
            navigationPager: (bool) ($data['navigation_pager'] ?? false),
        );
    }
}
