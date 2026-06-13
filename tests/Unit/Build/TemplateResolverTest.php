<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Build\TemplateResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringEndsWith;

final class TemplateResolverTest extends TestCase
{
    public function testResolvesFromDefaultTheme(): void
    {
        $resolver = $this->createResolver();

        $path = $resolver->resolve('entry');

        assertStringEndsWith('/themes/minimal/entry.php', $path);
    }

    public function testResolvesFromNamedThemeWhenSpecified(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-theme-test-' . uniqid();
        mkdir($tempDir, 0o755, true);
        file_put_contents($tempDir . '/entry.php', '<p>custom</p>');

        try {
            $registry = new ThemeRegistry();
            $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
            $registry->register(new Theme('custom', $tempDir));
            $resolver = new TemplateResolver($registry);

            $path = $resolver->resolve('entry', 'custom');

            assertSame($tempDir . '/entry.php', $path);
        } finally {
            unlink($tempDir . '/entry.php');
            rmdir($tempDir);
        }
    }

    public function testFallsBackToDefaultWhenNamedThemeLacksTemplate(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-theme-test-' . uniqid();
        mkdir($tempDir, 0o755, true);

        try {
            $registry = new ThemeRegistry();
            $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
            $registry->register(new Theme('custom', $tempDir));
            $resolver = new TemplateResolver($registry);

            $path = $resolver->resolve('entry', 'custom');

            assertStringEndsWith('/themes/minimal/entry.php', $path);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testThrowsWhenTemplateNotFound(): void
    {
        $resolver = $this->createResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template "nonexistent" not found.');
        $resolver->resolve('nonexistent');
    }

    public function testTemplateDirsReturnsAllThemePaths(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('default', '/a'));
        $registry->register(new Theme('custom', '/b'));
        $resolver = new TemplateResolver($registry);

        assertSame(['/a', '/b'], $resolver->templateDirs());
    }

    public function testResolveResourceThemeNameReturnsOwningTheme(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-theme-test-' . uniqid();
        mkdir($tempDir . '/assets', 0o755, true);
        file_put_contents($tempDir . '/assets/style.css', 'body{}');

        try {
            $registry = new ThemeRegistry();
            $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
            $registry->register(new Theme('custom', $tempDir));
            $resolver = new TemplateResolver($registry);

            assertSame('custom', $resolver->resolveResourceThemeName('assets/style.css', 'custom'));
        } finally {
            unlink($tempDir . '/assets/style.css');
            rmdir($tempDir . '/assets');
            rmdir($tempDir);
        }
    }

    public function testResolveResourceThemeNameFallsBackToDefaultTheme(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-theme-test-' . uniqid();
        mkdir($tempDir, 0o755, true);

        try {
            $registry = new ThemeRegistry();
            $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
            $registry->register(new Theme('custom', $tempDir));
            $resolver = new TemplateResolver($registry);

            assertSame('minimal', $resolver->resolveResourceThemeName('assets/style.css', 'custom'));
        } finally {
            rmdir($tempDir);
        }
    }

    private function createResolver(): TemplateResolver
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        return new TemplateResolver($registry);
    }
}
