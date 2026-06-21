<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeInitializer;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function PHPUnit\Framework\assertSame;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class ThemeInitializerTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/yiipress-theme-initializer-test-' . uniqid();
        mkdir($this->rootDir . '/source/partials', 0o755, true);
        mkdir($this->rootDir . '/source/assets', 0o755, true);
        file_put_contents($this->rootDir . '/source/entry.php', 'entry');
        file_put_contents($this->rootDir . '/source/partials/head.php', 'head');
        file_put_contents($this->rootDir . '/source/assets/style.css', 'style');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    public function testInitializesThemeFilesRecursively(): void
    {
        $copied = (new ThemeInitializer())->initialize(
            new Theme('minimal', $this->rootDir . '/source'),
            $this->rootDir . '/target',
        );

        assertSame(3, $copied);
        assertSame('entry', file_get_contents($this->rootDir . '/target/entry.php'));
        assertSame('head', file_get_contents($this->rootDir . '/target/partials/head.php'));
        assertSame('style', file_get_contents($this->rootDir . '/target/assets/style.css'));
    }

    public function testDoesNotOverwriteExistingFiles(): void
    {
        mkdir($this->rootDir . '/target');
        file_put_contents($this->rootDir . '/target/entry.php', 'existing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target file already exists');

        try {
            (new ThemeInitializer())->initialize(
                new Theme('minimal', $this->rootDir . '/source'),
                $this->rootDir . '/target',
            );
        } finally {
            assertSame('existing', file_get_contents($this->rootDir . '/target/entry.php'));
            self::assertFileDoesNotExist($this->rootDir . '/target/partials/head.php');
            self::assertFileDoesNotExist($this->rootDir . '/target/assets/style.css');
        }
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
