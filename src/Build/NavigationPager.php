<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;

final class NavigationPager
{
    /**
     * @param array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null $navigationPager
     * @param array{text: string, link: string}|false|null $previous
     * @param array{text: string, link: string}|false|null $next
     * @return array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null
     */
    public static function withOverrides(?array $navigationPager, array|false|null $previous, array|false|null $next): ?array
    {
        if ($navigationPager === null && $previous === null && $next === null) {
            return null;
        }

        return [
            'previous' => self::resolveOverride($navigationPager['previous'] ?? null, $previous),
            'next' => self::resolveOverride($navigationPager['next'] ?? null, $next),
        ];
    }

    /**
     * @return array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null
     */
    public static function forUrl(
        Navigation $navigation,
        string $menuName,
        string $currentUrl,
        string $language = 'en',
        string $defaultLanguage = 'en',
    ): ?array {
        $items = self::flatten($navigation->menu($menuName), $language, $defaultLanguage);
        $current = self::normalizeUrl($currentUrl);
        if ($items === [] || $current === '') {
            return null;
        }

        foreach ($items as $index => $item) {
            if (self::normalizeUrl($item['url']) !== $current) {
                continue;
            }

            return [
                'previous' => $items[$index - 1] ?? null,
                'next' => $items[$index + 1] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param list<NavigationItem> $items
     * @return list<array{title: string, url: string}>
     */
    private static function flatten(array $items, string $language, string $defaultLanguage): array
    {
        $result = [];

        foreach ($items as $item) {
            if (self::normalizeUrl($item->url) !== '') {
                $result[] = [
                    'title' => $item->resolveTitle($language, $defaultLanguage),
                    'url' => $item->url,
                ];
            }

            if ($item->children !== []) {
                array_push($result, ...self::flatten($item->children, $language, $defaultLanguage));
            }
        }

        return $result;
    }

    private static function normalizeUrl(string $url): string
    {
        if (
            $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
        ) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, './');
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * @param array{title: string, url: string}|null $inherited
     * @param array{text: string, link: string}|false|null $override
     * @return array{title: string, url: string}|null
     */
    private static function resolveOverride(?array $inherited, array|false|null $override): ?array
    {
        if ($override === null) {
            return $inherited;
        }

        if ($override === false) {
            return null;
        }

        return ['title' => $override['text'], 'url' => $override['link']];
    }
}
