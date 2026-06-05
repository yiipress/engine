<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;

use function array_filter;
use function array_is_list;
use function explode;
use function file_get_contents;
use function implode;
use function is_array;
use function is_scalar;
use function reset;
use function str_replace;
use function strtolower;
use function yaml_parse;

final class NavigationParser
{
    public function parse(string $filePath): Navigation
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidContentConfigException(
                "Cannot read navigation configuration file: $filePath",
                $filePath,
                'Check that the file exists and is readable by the build process.',
            );
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new InvalidContentConfigException(
                "Invalid YAML in navigation configuration file: $filePath",
                $filePath,
                "Fix the YAML syntax in $filePath, then run the build again.",
            );
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidContentConfigException(
                'The navigation configuration file must contain YAML key-value pairs.',
                $filePath,
                implode("\n", [
                    'Use menu mappings such as:',
                    'main:',
                    '  - title: Home',
                    '    url: /',
                ]),
            );
        }

        $menus = [];
        foreach ($data as $menuName => $items) {
            if (!is_array($items)) {
                continue;
            }
            $menus[(string) $menuName] = $this->parseItems($items);
        }

        return new Navigation(menus: $menus);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<NavigationItem>
     */
    private function parseItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            [$title, $titles] = $this->parseTitle($item['title'] ?? '');
            $children = isset($item['children']) && is_array($item['children'])
                ? $this->parseItems($item['children'])
                : [];
            $result[] = new NavigationItem(
                title: $title,
                url: (string) ($item['url'] ?? ''),
                children: $children,
                titles: $titles,
            );
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @return array{0: string, 1: array<string, string>}
     */
    private function parseTitle(mixed $value): array
    {
        if (!is_array($value)) {
            return [(string) $value, []];
        }

        $titles = [];
        foreach ($value as $language => $title) {
            if (!is_scalar($title)) {
                continue;
            }

            $titles[$this->normalizeLanguage((string) $language)] = (string) $title;
        }

        $titles = array_filter($titles, static fn (string $title): bool => $title !== '');
        if ($titles === []) {
            return ['', []];
        }

        return [(string) reset($titles), $titles];
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(str_replace('_', '-', $language));
        if ($normalized === '') {
            return 'en';
        }

        return explode('-', $normalized)[0];
    }
}
