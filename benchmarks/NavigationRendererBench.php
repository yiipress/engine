<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Content\Model\Navigation;
use App\Content\Model\NavigationItem;
use App\Render\NavigationRenderer;
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
}
