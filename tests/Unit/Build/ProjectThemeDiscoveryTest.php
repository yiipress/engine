<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Build\ProjectThemeDiscovery;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

final class ProjectThemeDiscoveryTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/yiipress-project-themes-test-' . uniqid();
        mkdir($this->rootDir . '/themes', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    public function testDiscoversProjectThemesInDeterministicOrder(): void
    {
        mkdir($this->rootDir . '/themes/zeta');
        mkdir($this->rootDir . '/themes/alpha');
        mkdir($this->rootDir . '/themes/docs_theme');
        file_put_contents($this->rootDir . '/themes/readme.md', 'not a theme');

        $themes = (new ProjectThemeDiscovery())->discover($this->rootDir . '/themes');

        assertSame(['alpha', 'docs_theme', 'zeta'], array_map(static fn (Theme $theme): string => $theme->name, $themes));
    }

    public function testSkipsInvalidThemeNames(): void
    {
        mkdir($this->rootDir . '/themes/good-theme');
        mkdir($this->rootDir . '/themes/.hidden');
        mkdir($this->rootDir . '/themes/bad.name');

        $themes = (new ProjectThemeDiscovery())->discover($this->rootDir . '/themes');

        assertCount(1, $themes);
        assertSame('good-theme', $themes[0]->name);
    }

    public function testReturnsEmptyListWhenThemesDirectoryIsMissing(): void
    {
        $themes = (new ProjectThemeDiscovery())->discover($this->rootDir . '/missing');

        assertSame([], $themes);
    }

    public function testRegistersThemesWithoutOverwritingExistingNames(): void
    {
        mkdir($this->rootDir . '/themes/minimal');
        mkdir($this->rootDir . '/themes/fancy');
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', '/built-in/minimal'));

        $registered = (new ProjectThemeDiscovery())->register($registry, $this->rootDir . '/themes');

        assertSame(1, $registered);
        assertSame('/built-in/minimal', $registry->get('minimal')->path);
        assertSame($this->rootDir . '/themes/fancy', $registry->get('fancy')->path);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
