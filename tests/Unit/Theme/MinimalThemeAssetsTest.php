<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function PHPUnit\Framework\assertStringContainsString;

final class MinimalThemeAssetsTest extends TestCase
{
    public function testStyleSupportsWrappedEntryTagsAndLongContentLinks(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.entry-tags,', $css);
        assertStringContainsString('flex-wrap: wrap;', $css);
        assertStringContainsString('.content a {', $css);
        assertStringContainsString('overflow-wrap: anywhere;', $css);
        assertStringContainsString('.content a.tag-link {', $css);
        assertStringContainsString('margin: 0 .25rem .5rem 0;', $css);
    }

    public function testStylePreservesExplicitContentImageHeight(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString(
            '.content img { max-width: 100%; border-radius: .5rem; margin: 1.25rem 0; }',
            $css,
        );
        assertStringContainsString('.content img:not([height]) { height: auto; }', $css);
    }

    public function testStyleSupportsDocsNavigationAndTocSidebars(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.docs-layout {', $css);
        assertStringContainsString('grid-template-columns: 16rem minmax(0, var(--max-width)) 14rem;', $css);
        assertStringContainsString('.docs-sidebar-nav .is-current > a {', $css);
        assertStringContainsString('.toc-sidebar-right {', $css);
    }
}
