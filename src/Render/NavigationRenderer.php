<?php

declare(strict_types=1);

namespace App\Render;

use App\Build\RelativePathHelper;
use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;

final class NavigationRenderer
{
    public static function render(
        Navigation $navigation,
        string $menuName,
        string $rootPath = './',
        string $language = 'en',
        string $defaultLanguage = 'en',
    ): string {
        $items = $navigation->menu($menuName);
        if ($items === []) {
            return '';
        }

        return '<nav>' . self::renderItems($items, $rootPath, $language, $defaultLanguage) . '</nav>';
    }

    /**
     * @param list<NavigationItem> $items
     */
    private static function renderItems(array $items, string $rootPath, string $language, string $defaultLanguage): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>';
            $url = str_starts_with($item->url, '/')
                ? RelativePathHelper::relativize($item->url, $rootPath)
                : $item->url;
            $title = $item->resolveTitle($language, $defaultLanguage);
            $attributes = '';
            if ($item->titles !== []) {
                $attributes = ' data-ui-menu-title="'
                    . htmlspecialchars((string) json_encode(
                        $item->titles,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                    ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" data-ui-menu-default="'
                    . htmlspecialchars($item->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '"';
            }
            $html .= '<a href="' . htmlspecialchars($url) . '"' . $attributes . '>'
                . htmlspecialchars($title) . '</a>';
            if ($item->children !== []) {
                $html .= self::renderItems($item->children, $rootPath, $language, $defaultLanguage);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
