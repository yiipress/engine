<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\Theme;
use App\Build\ThemeAssetCopier;
use App\Build\ThemeRegistry;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class ThemeAssetCopierTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-theme-asset-test-' . uniqid();
        mkdir($this->tempDir . '/output', 0o755, true);
        mkdir($this->tempDir . '/theme/assets', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testCopiesThemeAssetsToOutput(): void
    {
        file_put_contents($this->tempDir . '/theme/assets/style.css', 'body { color: red; }');

        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir . '/theme'));

        $copier = new ThemeAssetCopier();
        $copied = $copier->copy($registry, $this->tempDir . '/output');

        assertSame(1, $copied);
        assertFileExists($this->tempDir . '/output/assets/theme/style.css');
        assertStringContainsString('color: red', file_get_contents($this->tempDir . '/output/assets/theme/style.css'));
    }

    public function testReturnsZeroWhenNoAssetsDir(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('empty', $this->tempDir . '/nonexistent'));

        $copier = new ThemeAssetCopier();
        $copied = $copier->copy($registry, $this->tempDir . '/output');

        assertSame(0, $copied);
    }

    public function testCopiesNestedAssets(): void
    {
        mkdir($this->tempDir . '/theme/assets/fonts', 0o755, true);
        file_put_contents($this->tempDir . '/theme/assets/style.css', 'body {}');
        file_put_contents($this->tempDir . '/theme/assets/fonts/mono.woff2', 'font-data');

        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir . '/theme'));

        $copier = new ThemeAssetCopier();
        $copied = $copier->copy($registry, $this->tempDir . '/output');

        assertSame(2, $copied);
        assertFileExists($this->tempDir . '/output/assets/theme/style.css');
        assertFileExists($this->tempDir . '/output/assets/theme/fonts/mono.woff2');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
