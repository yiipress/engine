<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

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

    public function testImageViewerUsesThemeAwareBackground(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('--c-image-zoom-bg: rgba(255, 255, 255, 0.94);', $css);
        assertStringContainsString('--c-image-zoom-bg: rgba(0, 0, 0, 0.9);', $css);
        assertStringContainsString('background: var(--c-image-zoom-bg);', $css);
        assertStringContainsString('color: var(--c-image-zoom-control);', $css);
    }

    public function testStyleSupportsDocsNavigationAndTocSidebars(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.docs-layout {', $css);
        assertStringContainsString('grid-template-columns: 16rem minmax(0, var(--max-width)) 14rem;', $css);
        assertStringNotContainsString('.docs-layout:not(.docs-layout-with-toc)', $css);
        assertStringContainsString('.docs-sidebar-nav .is-current > a {', $css);
        assertStringContainsString('.toc-sidebar .is-current > a {', $css);
        assertStringContainsString('.toc-sidebar .is-current::before {', $css);
        assertStringContainsString('left: -1px;', $css);
        assertStringContainsString('.toc-sidebar-right {', $css);
    }

    public function testHeaderStaysOnOneRowBeforeDocsSidebarsCollapse(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('@media (max-width: 1100px) {', $css);
        assertStringContainsString('grid-template-columns: auto minmax(0, 1fr) auto;', $css);
        assertStringContainsString('grid-template-areas: "brand nav actions";', $css);
        assertStringContainsString('flex-wrap: nowrap;', $css);
        assertStringContainsString('overflow-x: auto;', $css);
        assertStringContainsString('@media (max-width: 640px) {', $css);
        assertStringContainsString('flex-wrap: wrap;', $css);
        assertStringContainsString('@media (max-width: 860px) {', $css);
        assertStringContainsString('grid-template-areas:', $css);
        assertStringContainsString('"brand actions"', $css);
        assertStringContainsString('"nav nav";', $css);
    }

    public function testStyleSupportsHeadingPermalinks(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.content .header-anchor {', $css);
        assertStringContainsString('content: "#";', $css);
        assertStringContainsString('.content .header-anchor:focus {', $css);
    }

    public function testStyleSupportsCodeCopyButtons(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.code-block {', $css);
        assertStringContainsString('.code-copy-button {', $css);
        assertStringContainsString('pointer-events: none;', $css);
        assertStringContainsString('.code-copy-button.copied {', $css);
        assertStringContainsString('pointer-events: auto;', $css);
    }

    public function testCodeCopyScriptEnhancesContentPreBlocks(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/code-copy.js');

        self::assertNotFalse($script);
        assertStringContainsString("document.querySelectorAll('.content pre')", $script);
        assertStringContainsString("button.className = 'code-copy-button';", $script);
        assertStringContainsString("navigator.clipboard.writeText(text)", $script);
        assertStringContainsString("document.execCommand('copy')", $script);
    }

    public function testStyleSupportsEntryNavigationPager(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.entry-pager {', $css);
        assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);', $css);
        assertStringContainsString('.entry-pager-link {', $css);
        assertStringContainsString('.entry-pager-title {', $css);
    }

    public function testStyleSupportsEntryPageMetadata(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.entry-page-meta {', $css);
        assertStringContainsString('.entry-last-updated {', $css);
        assertStringContainsString('.entry-page-actions {', $css);
        assertStringContainsString('.entry-page-action {', $css);
    }

    public function testStylePreventsWideContentTablesFromOverlappingSidebars(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.content { font-size: 1.0625rem; line-height: 1.8; min-width: 0; }', $css);
        assertStringContainsString(
            '.content table { display: block; width: 100%; max-width: 100%; overflow-x: auto;',
            $css,
        );
        assertStringContainsString('.docs-content {', $css);
        assertStringContainsString('overflow-wrap: anywhere;', $css);
    }

    public function testTocHighlightScriptTracksCurrentHeading(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/toc-highlight.js');

        self::assertNotFalse($script);
        assertStringContainsString("document.querySelectorAll('.toc-sidebar a[href^=\"#\"]')", $script);
        assertStringContainsString("activeItem.listItem.classList.add('is-current');", $script);
        assertStringContainsString("activeItem.link.setAttribute('aria-current', 'true');", $script);
        assertStringContainsString('Math.min(Math.max(window.innerHeight * 0.4, 160), 360)', $script);
        assertStringContainsString('scrollBottom >= documentHeight - 2', $script);
        assertStringContainsString('setActive(items[items.length - 1]);', $script);
    }
}
