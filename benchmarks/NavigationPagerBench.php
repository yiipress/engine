<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\NavigationPager;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

final class NavigationPagerBench
{
    private Navigation $navigation;

    public function __construct()
    {
        $items = [];
        for ($index = 0; $index < 100; $index++) {
            $items[] = new NavigationItem('Page ' . $index, '/guide/page-' . $index . '/', []);
        }
        $this->navigation = new Navigation(['sidebar' => $items]);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchResolveWithCustomOverride(): void
    {
        $pager = NavigationPager::forUrl($this->navigation, 'sidebar', '/guide/page-50/');
        NavigationPager::withOverrides(
            $pager,
            false,
            ['text' => 'Custom next', 'link' => '/guide/custom/'],
        );
    }
}
