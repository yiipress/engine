<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Entry;
use DateTimeImmutable;

use function is_array;

final readonly class EntryParser
{
    public function __construct(
        private FrontMatterParser $frontMatterParser,
        private FilenameParser $filenameParser,
        private array $authors = [],
    ) {}

    public function parse(string $filePath, string $collectionName): Entry
    {
        $result = $this->frontMatterParser->parse($filePath);
        $fields = $result['frontMatter'];
        $title = (string) ($fields['title'] ?? '');

        if ($title === '') {
            return new Entry(
                filePath: $filePath,
                collection: $collectionName,
                slug: '',
                title: '',
                date: null,
                draft: false,
                tags: [],
                inlineTags: [],
                categories: [],
                authors: [],
                summary: '',
                permalink: '',
                layout: '',
                theme: '',
                weight: 0,
                language: '',
                redirectTo: '',
                extra: [],
                bodyOffset: $result['bodyOffset'],
                bodyLength: $result['bodyLength'],
                image: '',
            );
        }

        $filenameParsed = $this->filenameParser->parse($filePath);

        $date = isset($fields['date'])
            ? new DateTimeImmutable((string) $fields['date'])
            : $filenameParsed['date'];

        $slug = (string) ($fields['slug'] ?? $filenameParsed['slug']);

        $frontMatterTags = isset($fields['tags']) && is_array($fields['tags'])
            ? array_values(array_map(strval(...), $fields['tags']))
            : [];

        $inlineTags = $this->extractInlineTags($filePath, $result['bodyOffset'], $result['bodyLength']);
        $tags = $this->mergeTags($frontMatterTags, $inlineTags);

        return new Entry(
            filePath: $filePath,
            collection: $collectionName,
            slug: $slug,
            title: $title,
            date: $date,
            draft: (bool) ($fields['draft'] ?? false),
            tags: $tags,
            inlineTags: $inlineTags,
            categories: isset($fields['categories']) && is_array($fields['categories'])
                ? array_values(array_map(strval(...), $fields['categories']))
                : [],
            authors: isset($fields['authors']) && is_array($fields['authors'])
                ? array_values(array_map(strval(...), $fields['authors']))
                : [],
            summary: (string) ($fields['summary'] ?? ''),
            permalink: (string) ($fields['permalink'] ?? ''),
            layout: (string) ($fields['layout'] ?? ''),
            theme: (string) ($fields['theme'] ?? ''),
            weight: (int) ($fields['weight'] ?? 0),
            language: (string) ($fields['language'] ?? ''),
            redirectTo: (string) ($fields['redirect_to'] ?? ''),
            extra: isset($fields['extra']) && is_array($fields['extra'])
                ? $fields['extra']
                : [],
            bodyOffset: $result['bodyOffset'],
            bodyLength: $result['bodyLength'],
            image: (string) ($fields['image'] ?? ''),
            translationKey: (string) ($fields['translation_key'] ?? ''),
        );
    }

    /**
     * Extract inline tags from body content (e.g., #tag in markdown).
     *
     * @return list<string>
     */
    private function extractInlineTags(string $filePath, int $bodyOffset, int $bodyLength): array
    {
        if ($bodyLength <= 0) {
            return [];
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return [];
        }

        $body = '';
        try {
            if (fseek($handle, $bodyOffset) === 0) {
                $read = fread($handle, $bodyLength);
                if ($read !== false) {
                    $body = $read;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($body === '') {
            return [];
        }

        preg_match_all('/#([\w-]+)/u', strip_tags($body), $matches);
        return array_map(strtolower(...), $matches[1]);
    }

    /**
     * Merge front matter and inline tags, removing duplicates (case-insensitive).
     *
     * @param list<string> $frontMatterTags
     * @param list<string> $inlineTags
     * @return list<string>
     */
    private function mergeTags(array $frontMatterTags, array $inlineTags): array
    {
        $seen = [];
        $result = [];

        foreach ($frontMatterTags as $tag) {
            $lower = strtolower($tag);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $result[] = $tag;
            }
        }

        foreach ($inlineTags as $tag) {
            $lower = strtolower($tag);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $result[] = $tag;
            }
        }

        return $result;
    }
}
