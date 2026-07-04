<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\AssetMinifier;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class AssetMinifierTest extends TestCase
{
    public function testSupportsCssAndJavaScriptAssets(): void
    {
        assertTrue(AssetMinifier::supports('theme/app.css'));
        assertTrue(AssetMinifier::supports('theme/app.JS'));
        assertFalse(AssetMinifier::supports('theme/logo.svg'));
    }

    public function testMinifiesCssWithoutBreakingUrlsOrStrings(): void
    {
        $css = <<<'CSS'
            /* heading */
            body {
                color: red;
                background: url(https://example.com/assets/banner.png);
                content: "a /* not a comment */ b";
            }
            CSS;

        assertSame(
            'body{color:red;background:url(https://example.com/assets/banner.png);content:"a /* not a comment */ b"}',
            AssetMinifier::minify('app.css', $css),
        );
    }

    public function testMinifiesCssWithoutBreakingMediaQueries(): void
    {
        $css = <<<'CSS'
            @media screen and (min-width: 600px) {
                body { color: red; }
            }
            CSS;

        assertSame(
            '@media screen and (min-width:600px){body{color:red}}',
            AssetMinifier::minify('app.css', $css),
        );
    }

    public function testMinifiesCssWithoutBreakingSelectorOrValueWhitespace(): void
    {
        $css = <<<'CSS'
            .site-header .container {
                display: grid;
                padding: 0 .125rem;
            }

            .container:has(.docs-layout) {
                max-width: calc(100% - (var(--page-gutter) * 2));
            }

            .content :is(h1, h2) .header-anchor {
                margin: 1rem 1.25rem;
            }
            CSS;

        assertSame(
            '.site-header .container{display:grid;padding:0 .125rem}.container:has(.docs-layout){max-width:calc(100% - (var(--page-gutter) * 2))}.content :is(h1,h2) .header-anchor{margin:1rem 1.25rem}',
            AssetMinifier::minify('app.css', $css),
        );
    }

    public function testMinifiesJavaScriptWithoutBreakingRegexOrStrings(): void
    {
        $js = <<<'JS'
            const url = "https://example.com"; // comment
            const re = /https?:\/\/example\.com\/[a-z]+/gi;
            const block = "/* not a comment */";
            if (re.test(url)) {
                console.log(block); /* done */
            }
            JS;

        assertSame(
            <<<'JS'
            const url = "https://example.com"; 
            const re = /https?:\/\/example\.com\/[a-z]+/gi;
            const block = "/* not a comment */";
            if (re.test(url)) {
             console.log(block); 
            }
            JS,
            AssetMinifier::minify('app.js', $js),
        );
    }
}
