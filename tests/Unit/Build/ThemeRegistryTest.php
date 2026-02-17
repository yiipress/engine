<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\Theme;
use App\Build\ThemeRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class ThemeRegistryTest extends TestCase
{
    public function testRegisterAndGetTheme(): void
    {
        $registry = new ThemeRegistry();
        $theme = new Theme('default', '/path/to/templates');
        $registry->register($theme);

        assertSame($theme, $registry->get('default'));
    }

    public function testHasReturnsTrueForRegisteredTheme(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', '/path'));

        assertTrue($registry->has('default'));
    }

    public function testHasReturnsFalseForUnregisteredTheme(): void
    {
        $registry = new ThemeRegistry();

        assertFalse($registry->has('missing'));
    }

    public function testGetThrowsForUnregisteredTheme(): void
    {
        $registry = new ThemeRegistry();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Theme "missing" is not registered.');
        $registry->get('missing');
    }

    public function testAllReturnsAllRegisteredThemes(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', '/a'));
        $registry->register(new Theme('custom', '/b'));

        assertCount(2, $registry->all());
    }

    public function testRegisterOverwritesExistingTheme(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', '/old'));
        $registry->register(new Theme('default', '/new'));

        assertSame('/new', $registry->get('default')->path);
        assertCount(1, $registry->all());
    }
}
