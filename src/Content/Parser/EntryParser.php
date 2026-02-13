<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Entry;
use DateTimeImmutable;

final class EntryParser
{
    public function __construct(
        private FrontMatterParser $frontMatterParser,
        private FilenameParser $filenameParser,
    ) {}

    public function parse(string $filePath, string $collectionName): Entry
    {
        $result = $this->frontMatterParser->parse($filePath);
        $fields = $result['frontMatter'];
        $filenameParsed = $this->filenameParser->parse($filePath);

        $date = isset($fields['date'])
            ? new DateTimeImmutable((string) $fields['date'])
            : $filenameParsed['date'];

        $slug = (string) ($fields['slug'] ?? $filenameParsed['slug']);

        return new Entry(
            filePath: $filePath,
            collection: $collectionName,
            slug: $slug,
            title: (string) ($fields['title'] ?? ''),
            date: $date,
            draft: (bool) ($fields['draft'] ?? false),
            tags: isset($fields['tags']) && is_array($fields['tags'])
                ? array_values(array_map(strval(...), $fields['tags']))
                : [],
            categories: isset($fields['categories']) && is_array($fields['categories'])
                ? array_values(array_map(strval(...), $fields['categories']))
                : [],
            authors: isset($fields['authors']) && is_array($fields['authors'])
                ? array_values(array_map(strval(...), $fields['authors']))
                : [],
            summary: (string) ($fields['summary'] ?? ''),
            permalink: (string) ($fields['permalink'] ?? ''),
            layout: (string) ($fields['layout'] ?? ''),
            weight: (int) ($fields['weight'] ?? 0),
            language: (string) ($fields['language'] ?? ''),
            redirectTo: (string) ($fields['redirect_to'] ?? ''),
            extra: isset($fields['extra']) && is_array($fields['extra'])
                ? $fields['extra']
                : [],
            bodyOffset: $result['bodyOffset'],
            bodyLength: $result['bodyLength'],
        );
    }
}
