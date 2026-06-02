<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\NavigationPager;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\NavigationItem;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class NavigationPagerTest extends TestCase
{
    public function testResolvesPreviousAndNextFromNavigationOrder(): void
    {
        $pager = NavigationPager::forUrl($this->createNavigation(), 'sidebar', '/content/');

        self::assertNotNull($pager);
        assertSame(['title' => 'Configuration', 'url' => '/configuration/'], $pager['previous']);
        assertSame(['title' => 'Importing content', 'url' => '/importing-content/'], $pager['next']);
    }

    public function testResolvesEdges(): void
    {
        $first = NavigationPager::forUrl($this->createNavigation(), 'sidebar', '/');
        $last = NavigationPager::forUrl($this->createNavigation(), 'sidebar', '/deployment/');

        self::assertNotNull($first);
        self::assertNotNull($last);
        assertNull($first['previous']);
        assertSame(['title' => 'Quickstart', 'url' => '/quickstart/'], $first['next']);
        assertSame(['title' => 'Preview', 'url' => '/preview/'], $last['previous']);
        assertNull($last['next']);
    }

    public function testSkipsExternalAndSectionItems(): void
    {
        $pager = NavigationPager::forUrl($this->createNavigation(), 'sidebar', '/quickstart/');

        self::assertNotNull($pager);
        assertSame(['title' => 'Overview', 'url' => '/'], $pager['previous']);
        assertSame(['title' => 'Configuration', 'url' => '/configuration/'], $pager['next']);
    }

    public function testReturnsNullWhenCurrentPageIsNotInNavigation(): void
    {
        assertNull(NavigationPager::forUrl($this->createNavigation(), 'sidebar', '/missing/'));
    }

    private function createNavigation(): Navigation
    {
        return new Navigation([
            'sidebar' => [
                new NavigationItem('Start', '', [
                    new NavigationItem('Overview', '/', []),
                    new NavigationItem('Quickstart', '/quickstart/', []),
                    new NavigationItem('GitHub', 'https://github.com/yiipress/engine', []),
                    new NavigationItem('Configuration', '/configuration/', []),
                    new NavigationItem('Content', '/content/', []),
                    new NavigationItem('Importing content', '/importing-content/', []),
                ]),
                new NavigationItem('Building', '', [
                    new NavigationItem('Preview', '/preview/', []),
                    new NavigationItem('Deployment', '/deployment/', []),
                ]),
            ],
        ]);
    }
}
