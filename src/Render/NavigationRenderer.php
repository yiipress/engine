<?php

declare(strict_types=1);

namespace App\Render;

use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;

final class NavigationRenderer
{
    public static function render(Navigation $navigation, string $menuName): string
    {
        $items = $navigation->menu($menuName);
        if ($items === []) {
            return '';
        }

        return '<nav>' . self::renderItems($items) . '</nav>';
    }

    /**
     * @param list<NavigationItem> $items
     */
    private static function renderItems(array $items): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>';
            $html .= '<a href="' . htmlspecialchars($item->url) . '">'
                . htmlspecialchars($item->title) . '</a>';
            if ($item->children !== []) {
                $html .= self::renderItems($item->children);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
