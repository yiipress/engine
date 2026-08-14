<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Collection;

use function array_is_list;
use function file_get_contents;
use function implode;
use function is_array;
use function max;
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
            title: ValueNormalizer::string($data['title'] ?? null, $collectionName),
            description: ValueNormalizer::string($data['description'] ?? null),
            permalink: ValueNormalizer::string($data['permalink'] ?? null, '/:collection/:slug/'),
            sortBy: ValueNormalizer::string($data['sort_by'] ?? null, 'date'),
            sortOrder: ValueNormalizer::string($data['sort_order'] ?? null, 'desc'),
            entriesPerPage: ValueNormalizer::integer($data['entries_per_page'] ?? null, 10),
            feed: ValueNormalizer::boolean($data['feed'] ?? null, false),
            listing: ValueNormalizer::boolean($data['listing'] ?? null, true),
            order: ValueNormalizer::stringList($data['order'] ?? null),
            navigationPager: ValueNormalizer::boolean($data['navigation_pager'] ?? null, false),
            feedLimit: max(0, ValueNormalizer::integer($data['feed_limit'] ?? null, 20)),
        );
    }
}
