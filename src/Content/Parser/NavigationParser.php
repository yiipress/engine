<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;
use RuntimeException;

use function array_filter;
use function explode;
use function file_get_contents;
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
            throw new RuntimeException("Cannot read file: $filePath");
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new RuntimeException("Invalid YAML in file: $filePath");
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
