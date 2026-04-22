<?php

declare(strict_types=1);

namespace App\Tests\Unit\Theme;

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
}
