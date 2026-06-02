<?php

declare(strict_types=1);

namespace YiiPress\Render;

use YiiPress\Build\RelativePathHelper;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;

final class NavigationRenderer
{
    public static function render(
        Navigation $navigation,
        string $menuName,
        string $rootPath = './',
        string $language = 'en',
        string $defaultLanguage = 'en',
        string $class = '',
        string $currentUrl = '',
    ): string {
        $items = $navigation->menu($menuName);
        if ($items === []) {
            return '';
        }

        [$html] = self::renderItems($items, $rootPath, $language, $defaultLanguage, $currentUrl);
        $classAttribute = $class !== ''
            ? ' class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            : '';

        return '<nav' . $classAttribute . '>' . $html . '</nav>';
    }

    public static function menuContainsUrl(Navigation $navigation, string $menuName, string $currentUrl): bool
    {
        if ($currentUrl === '') {
            return false;
        }

        return self::itemsContainCurrent($navigation->menu($menuName), $currentUrl);
    }

    /**
     * @param list<NavigationItem> $items
     * @return array{0: string, 1: bool}
     */
    private static function renderItems(
        array $items,
        string $rootPath,
        string $language,
        string $defaultLanguage,
        string $currentUrl,
    ): array
    {
        $html = '<ul>';
        $hasCurrent = false;
        foreach ($items as $item) {
            [$childrenHtml, $childHasCurrent] = $item->children !== []
                ? self::renderItems($item->children, $rootPath, $language, $defaultLanguage, $currentUrl)
                : ['', false];
            $isCurrent = self::isCurrentUrl($item->url, $currentUrl);
            $hasCurrent = $hasCurrent || $isCurrent || $childHasCurrent;
            $classes = [];
            if ($isCurrent) {
                $classes[] = 'is-current';
            }
            if ($childHasCurrent) {
                $classes[] = 'is-active-ancestor';
            }
            $classAttribute = $classes !== []
                ? ' class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                : '';

            $html .= '<li' . $classAttribute . '>';
            $url = str_starts_with($item->url, '/')
                ? RelativePathHelper::relativize($item->url, $rootPath)
                : $item->url;
            $title = $item->resolveTitle($language, $defaultLanguage);
            $attributes = self::localizedTitleAttributes($item);
            if ($isCurrent) {
                $attributes .= ' aria-current="page"';
            }

            if ($item->url === '') {
                $html .= '<span class="nav-section-title"' . $attributes . '>'
                    . htmlspecialchars($title) . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($url) . '"' . $attributes . '>'
                    . htmlspecialchars($title) . '</a>';
            }
            $html .= $childrenHtml;
            $html .= '</li>';
        }
        $html .= '</ul>';

        return [$html, $hasCurrent];
    }

    /**
     * @param list<NavigationItem> $items
     */
    private static function itemsContainCurrent(array $items, string $currentUrl): bool
    {
        foreach ($items as $item) {
            if (
                self::isCurrentUrl($item->url, $currentUrl)
                || self::itemsContainCurrent($item->children, $currentUrl)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function isCurrentUrl(string $url, string $currentUrl): bool
    {
        $normalizedUrl = self::normalizeUrl($url);
        $normalizedCurrentUrl = self::normalizeUrl($currentUrl);

        return $normalizedUrl !== '' && $normalizedUrl === $normalizedCurrentUrl;
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

    private static function localizedTitleAttributes(NavigationItem $item): string
    {
        if ($item->titles === []) {
            return '';
        }

        return ' data-ui-menu-title="'
            . htmlspecialchars((string) json_encode(
                $item->titles,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-ui-menu-default="'
            . htmlspecialchars($item->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '"';
    }
}
