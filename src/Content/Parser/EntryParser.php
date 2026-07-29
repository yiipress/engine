<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Entry;
use DateMalformedStringException;
use DateTimeImmutable;

use function is_array;
use function is_bool;
use function is_string;

final readonly class EntryParser
{
    public function __construct(
        private FrontMatterParser $frontMatterParser,
        private FilenameParser $filenameParser,
        private array $authors = [],
    ) {}

    public function parse(string $filePath, string $collectionName, string $language = ''): Entry
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

        try {
            $date = isset($fields['date'])
                ? new DateTimeImmutable((string) $fields['date'])
                : $filenameParsed['date'];
        } catch (DateMalformedStringException) {
            throw new InvalidContentConfigException(
                "Invalid date in front matter: $filePath",
                $filePath,
                'Use a date value accepted by PHP DateTimeImmutable, for example: date: 2024-03-15',
            );
        }

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
            language: (string) ($fields['language'] ?? $language),
            redirectTo: (string) ($fields['redirect_to'] ?? ''),
            extra: isset($fields['extra']) && is_array($fields['extra'])
                ? $fields['extra']
                : [],
            bodyOffset: $result['bodyOffset'],
            bodyLength: $result['bodyLength'],
            image: (string) ($fields['image'] ?? ''),
            translationKey: (string) ($fields['translation_key'] ?? ''),
            showTitle: (bool) ($fields['showTitle'] ?? true),
            aliases: isset($fields['aliases']) && is_array($fields['aliases'])
                ? array_values(array_map(strval(...), $fields['aliases']))
                : [],
            previous: $this->parsePagerOverride($fields, 'previous', $filePath),
            next: $this->parsePagerOverride($fields, 'next', $filePath),
            editLink: $this->parseEditLink($fields, $filePath),
            lastUpdated: $this->parseBooleanOverride($fields, 'lastUpdated', $filePath),
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function parseEditLink(array $fields, string $filePath): ?bool
    {
        return $this->parseBooleanOverride($fields, 'editLink', $filePath);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function parseBooleanOverride(array $fields, string $name, string $filePath): ?bool
    {
        if (!array_key_exists($name, $fields) || $fields[$name] === null) {
            return null;
        }
        if (!is_bool($fields[$name])) {
            throw new InvalidContentConfigException(
                "Invalid \"$name\" visibility override in front matter: $filePath",
                $filePath,
                "Omit \"$name\" to inherit it, or set it to true or false.",
            );
        }

        return $fields[$name];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{text: string, link: string}|false|null
     */
    private function parsePagerOverride(array $fields, string $direction, string $filePath): array|false|null
    {
        if (!array_key_exists($direction, $fields)) {
            return null;
        }

        $value = $fields[$direction];
        if ($value === false) {
            return false;
        }

        if (
            !is_array($value)
            || count($value) !== 2
            || !array_key_exists('text', $value)
            || !array_key_exists('link', $value)
            || !is_string($value['text'])
            || trim($value['text']) === ''
            || !is_string($value['link'])
            || trim($value['link']) === ''
        ) {
            throw new InvalidContentConfigException(
                sprintf('Invalid "%s" pager override in front matter: %s', $direction, $filePath),
                $filePath,
                sprintf(
                    'Omit "%1$s" to inherit it, set "%1$s: false" to disable it, or use "%1$s: {text: Title, link: /page/}".',
                    $direction,
                ),
            );
        }

        $link = trim($value['link']);
        if (!$this->isSafePagerUrl($link)) {
            throw new InvalidContentConfigException(
                sprintf('Unsafe URL in "%s" pager override: %s', $direction, $filePath),
                $filePath,
                'Use a relative or root-relative internal URL, or an absolute http:// or https:// URL.',
            );
        }

        return ['text' => trim($value['text']), 'link' => $link];
    }

    private function isSafePagerUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || str_starts_with($url, '//')) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null) {
            return true;
        }
        if ($scheme === false || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
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
