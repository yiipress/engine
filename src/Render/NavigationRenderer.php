<?php

declare(strict_types=1);

namespace App\Render;

use App\Build\RelativePathHelper;
use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;

final class NavigationRenderer
{
    public static function render(Navigation $navigation, string $menuName, string $rootPath = './'): string
    {
        $items = $navigation->menu($menuName);
        if ($items === []) {
            return '';
        }

        return '<nav>' . self::renderItems($items, $rootPath) . '</nav>';
    }

    /**
     * @param list<NavigationItem> $items
     */
    private static function renderItems(array $items, string $rootPath): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>';
            $url = str_starts_with($item->url, '/')
                ? RelativePathHelper::relativize($item->url, $rootPath)
                : $item->url;
            $html .= '<a href="' . htmlspecialchars($url) . '">'
                . htmlspecialchars($item->title) . '</a>';
            if ($item->children !== []) {
                $html .= self::renderItems($item->children, $rootPath);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
