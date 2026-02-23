<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;
use RuntimeException;

use function file_get_contents;
use function is_array;
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
            $children = isset($item['children']) && is_array($item['children'])
                ? $this->parseItems($item['children'])
                : [];
            $result[] = new NavigationItem(
                title: (string) ($item['title'] ?? ''),
                url: (string) ($item['url'] ?? ''),
                children: $children,
            );
        }
        return $result;
    }
}
