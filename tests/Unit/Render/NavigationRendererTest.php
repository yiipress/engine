<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Render;

use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;
use YiiPress\Render\NavigationRenderer;
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
        assertStringContainsString('<a href="./">Home</a>', $html);
        assertStringContainsString('<a href="./blog/">Blog</a>', $html);
    }

    public function testRenderLocalizedMenu(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(
                    title: 'About',
                    url: '/about/',
                    children: [],
                    titles: ['en' => 'About', 'ru' => 'О сайте'],
                ),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'main', './', 'ru', 'en');

        assertStringContainsString(
            '<a href="./about/" data-ui-menu-title="{&quot;en&quot;:&quot;About&quot;,&quot;ru&quot;:&quot;О сайте&quot;}" data-ui-menu-default="About">О сайте</a>',
            $html,
        );
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

        assertStringContainsString('<a href="./docs/">Docs</a>', $html);
        assertStringContainsString('<a href="./docs/getting-started/">Getting Started</a>', $html);
        assertStringContainsString('<ul><li><a href="./docs/getting-started/">', $html);
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

    public function testRenderEscapesTextAndAttributesWithHtml5Rules(): void
    {
        $navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(title: 'Docs "Intro" & <Start>', url: '/docs/<intro>/?q=a&b="c"', children: []),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'main', './', 'en', 'en', 'docs"sidebar');

        assertStringContainsString('<nav class="docs&quot;sidebar">', $html);
        assertStringContainsString(
            '<a href="./docs/&lt;intro&gt;/?q=a&amp;b=&quot;c&quot;">Docs "Intro" &amp; &lt;Start&gt;</a>',
            $html,
        );
    }

    public function testRenderMarksCurrentAndAncestorItems(): void
    {
        $navigation = new Navigation(menus: [
            'sidebar' => [
                new NavigationItem(
                    title: 'Guide',
                    url: '',
                    children: [
                        new NavigationItem(title: 'Intro', url: '/guide/intro/', children: []),
                    ],
                ),
            ],
        ]);

        $html = NavigationRenderer::render($navigation, 'sidebar', '../', 'en', 'en', 'docs-sidebar-nav', '/guide/intro/');

        assertStringContainsString('<nav class="docs-sidebar-nav">', $html);
        assertStringContainsString('<li class="is-active-ancestor"><span class="nav-section-title">Guide</span>', $html);
        assertStringContainsString('<li class="is-current"><a href="../guide/intro/" aria-current="page">Intro</a>', $html);
    }

    public function testMenuContainsUrlOnlyForConfiguredMenuItems(): void
    {
        $navigation = new Navigation(menus: [
            'sidebar' => [
                new NavigationItem(title: 'Intro', url: '/guide/intro/', children: []),
            ],
        ]);

        self::assertTrue(NavigationRenderer::menuContainsUrl($navigation, 'sidebar', '/guide/intro/'));
        self::assertFalse(NavigationRenderer::menuContainsUrl($navigation, 'sidebar', '/blog/post/'));
    }
}
