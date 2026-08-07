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
        assertStringContainsString('grid-template-columns: 16rem minmax(0, var(--max-width));', $css);
        assertStringContainsString('.docs-layout.docs-layout-with-toc {', $css);
        assertStringContainsString('grid-template-columns: 16rem minmax(0, var(--max-width)) 14rem;', $css);
        assertStringContainsString('.container:has(.docs-layout-with-toc) {', $css);
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

    public function testStyleSupportsSemanticFaqQuestionsInBothColorSchemes(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('.faq-section {', $css);
        assertStringContainsString('.faq-question {', $css);
        assertStringContainsString('border-top: 1px solid var(--c-border);', $css);
        assertStringContainsString('.faq-question summary {', $css);
        assertStringContainsString('color: var(--c-text);', $css);
        assertStringContainsString('.faq-question summary:hover {', $css);
        assertStringContainsString('color: var(--c-link);', $css);
        assertStringContainsString('.faq-question:last-child {', $css);
        assertStringContainsString('border-bottom: 1px solid var(--c-border);', $css);
        assertStringContainsString('.faq-answer {', $css);
        assertStringContainsString('padding-top: .75rem;', $css);
        assertStringContainsString('.faq-answer > :first-child {', $css);
        assertStringContainsString('margin-top: 0;', $css);
        assertStringContainsString('.faq-answer > :last-child {', $css);
        assertStringContainsString('margin-bottom: 0;', $css);
    }

    public function testStyleSupportsAdmonitionsInBothColorSchemes(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString('--c-admonition-note: #0969da;', $css);
        assertStringContainsString('--c-admonition-note: #4493f8;', $css);
        assertStringContainsString('--c-admonition-note-bg: #ddf4ff;', $css);
        assertStringContainsString('--c-admonition-tip-bg: #dafbe1;', $css);
        assertStringContainsString('--c-admonition-important-bg: #fbefff;', $css);
        assertStringContainsString('--c-admonition-warning-bg: #fff8c5;', $css);
        assertStringContainsString('--c-admonition-caution-bg: #ffebe9;', $css);
        assertStringContainsString('--c-admonition-note-bg: rgba(56, 139, 253, .1);', $css);
        assertStringContainsString('--c-admonition-tip-bg: rgba(46, 160, 67, .15);', $css);
        assertStringContainsString('--c-admonition-important-bg: rgba(163, 113, 247, .15);', $css);
        assertStringContainsString('--c-admonition-warning-bg: rgba(187, 128, 9, .15);', $css);
        assertStringContainsString('--c-admonition-caution-bg: rgba(248, 81, 73, .1);', $css);
        assertStringContainsString('--c-admonition-tip:', $css);
        assertStringContainsString('--c-admonition-important:', $css);
        assertStringContainsString('--c-admonition-warning:', $css);
        assertStringContainsString('--c-admonition-caution:', $css);
        assertStringContainsString('.content div[class^="admonition-"] {', $css);
        assertStringContainsString('.content .admonition-title {', $css);
        assertStringContainsString('background: var(--admonition-bg);', $css);
        assertStringContainsString('color-mix(in srgb, var(--admonition-color) 24%, transparent);', $css);
        assertStringContainsString('content: "⚠";', $css);
        assertStringContainsString('.content .admonition-caution .admonition-title::before', $css);
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
        assertStringContainsString('.code-language-label {', $css);
        assertStringContainsString('.code-block:hover .code-language-label,', $css);
        assertStringContainsString('.code-copy-button {', $css);
        assertStringContainsString('pointer-events: none;', $css);
        assertStringContainsString('.code-copy-button.copied {', $css);
        assertStringContainsString('pointer-events: auto;', $css);
    }

    public function testStyleKeepsHighlightedCodeReadableInDarkMode(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        assertStringContainsString(
            '[data-theme="dark"] .content pre[style] { color: var(--c-text) !important; }',
            $css,
        );
    }

    public function testCodeLanguageLabelMatchesCodeBlock(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        self::assertSame(1, preg_match('/\.code-block \{(?<rule>[^}]*)}/', $css, $blockMatches));
        self::assertSame(1, preg_match('/\.code-block pre \{(?<rule>[^}]*)}/', $css, $preMatches));
        self::assertSame(1, preg_match('/\.code-language-label \{(?<rule>[^}]*)}/', $css, $matches));

        assertStringContainsString('--code-language-label-width: 8rem;', $blockMatches['rule']);
        assertStringContainsString(
            'padding-right: calc(var(--code-language-label-width) + 1.5rem);',
            $preMatches['rule'],
        );

        $rule = $matches['rule'];
        assertStringContainsString('max-width: var(--code-language-label-width);', $rule);
        assertStringContainsString('padding: .25rem .375rem;', $rule);
        assertStringContainsString('background: var(--c-code-bg);', $rule);
        assertStringContainsString('font-weight: 400;', $rule);
        assertStringContainsString('overflow: hidden;', $rule);
        assertStringContainsString('text-overflow: ellipsis;', $rule);
        assertStringContainsString('white-space: nowrap;', $rule);
    }

    public function testCopyButtonTextAlignsWithLanguageLabel(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/style.css');

        self::assertNotFalse($css);
        self::assertSame(1, preg_match('/\.code-language-label \{(?<rule>[^}]*)}/', $css, $labelMatches));
        self::assertSame(1, preg_match('/\.code-copy-button \{(?<rule>[^}]*)}/', $css, $buttonMatches));

        $labelRule = $labelMatches['rule'];
        $buttonRule = $buttonMatches['rule'];

        $sharedDeclarations = [
            'top: .25rem;',
            'right: .25rem;',
            'font-size: .75rem;',
            'font-weight: 400;',
            'line-height: 1;',
        ];
        foreach ($sharedDeclarations as $declaration) {
            assertStringContainsString($declaration, $labelRule);
        }
        $buttonDeclarations = [
            'top: .25rem;',
            'right: .25rem;',
            'height: 1.25rem;',
            'font: 400 .75rem/1 var(--font-sans);',
        ];
        foreach ($buttonDeclarations as $declaration) {
            assertStringContainsString($declaration, $buttonRule);
        }
    }

    public function testCodeCopyScriptEnhancesContentPreBlocks(): void
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/themes/minimal/assets/code-copy.js');

        self::assertNotFalse($script);
        assertStringContainsString("document.querySelectorAll('.content pre')", $script);
        assertStringContainsString("const existingWrapper = pre.closest('.code-block');", $script);
        assertStringContainsString("if (existingWrapper?.querySelector('.code-copy-button'))", $script);
        assertStringContainsString('const wrapper = existingWrapper ?? document.createElement', $script);
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
