<?php

declare(strict_types=1);

namespace App\Tests\Unit\Render;

use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;
use App\Render\NavigationRenderer;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class NavigationRendererTest extends TestCase
{
    public function testRenderEmptyMenu(): void
    {
        $navigation = new Navigation(menus: []);

        assertSame('', NavigationRenderer::render($navigation, 'main'));
    }

    public function testRenderSimpleMenu(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(title: 'Home', url: '/', children: []),
                new NavigationItem(title: 'Blog', url: '/blog/', children: []),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'main');

        assertStringContainsString('<nav>', $html);
        assertStringContainsString('<a href="/">Home</a>', $html);
        assertStringContainsString('<a href="/blog/">Blog</a>', $html);
    }

    public function testRenderNestedMenu(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(
                    title: 'Docs',
                    url: '/docs/',
                    children: [
                        new NavigationItem(title: 'Getting Started', url: '/docs/getting-started/', children: []),
                    ],
                ),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'main');

        assertStringContainsString('<a href="/docs/">Docs</a>', $html);
        assertStringContainsString('<a href="/docs/getting-started/">Getting Started</a>', $html);
        assertStringContainsString('<ul><li><a href="/docs/getting-started/">', $html);
    }

    public function testRenderNonExistentMenu(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(title: 'Home', url: '/', children: []),
            ],
        ]);

        assertSame('', NavigationRenderer::render($navigation, 'sidebar'));
    }

    public function testHtmlSpecialCharsInTitleAndUrl(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(title: 'A & B', url: '/a&b/', children: []),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'main');

        assertStringContainsString('A &amp; B', $html);
        assertStringContainsString('/a&amp;b/', $html);
    }
}
