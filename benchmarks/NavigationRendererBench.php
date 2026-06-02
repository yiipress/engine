<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;
use YiiPress\Render\NavigationRenderer;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

final class NavigationRendererBench
{
    private Navigation $navigation;

    public function __construct()
    {
        $this->navigation = new Navigation(menus: [
            'main' => [
                new NavigationItem(
                    title: 'About',
                    url: '/about/',
                    children: [
                        new NavigationItem(
                            title: 'Getting Started',
                            url: '/docs/getting-started/',
                            children: [],
                            titles: ['en' => 'Getting Started', 'ru' => 'Быстрый старт'],
                        ),
                    ],
                    titles: ['en' => 'About', 'ru' => 'О сайте'],
                ),
            ],
        ]);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderLocalizedNavigation(): void
    {
        NavigationRenderer::render($this->navigation, 'main', './', 'ru', 'en');
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderLocalizedNavigationWithCurrentUrl(): void
    {
        NavigationRenderer::render($this->navigation, 'main', './', 'ru', 'en', '', '/docs/getting-started/');
    }
}
