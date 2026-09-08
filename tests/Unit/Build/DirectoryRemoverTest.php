<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\DirectoryRemover;
use PHPUnit\Framework\TestCase;

final class DirectoryRemoverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-remove-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->tempDir);
    }

    public function testRemovesNestedDirectoriesHiddenFilesAndZeroFilename(): void
    {
        $root = $this->tempDir . '/output';
        mkdir($root . '/nested/empty', 0o755, true);
        file_put_contents($root . '/nested/index.html', 'page');
        file_put_contents($root . '/.yiipress-build', 'marker');
        file_put_contents($root . '/0', 'zero');
        DirectoryRemover::remove($root);
        self::assertDirectoryDoesNotExist($root);
        self::assertDirectoryExists($this->tempDir);
    }

    public function testMissingDirectoryIsIgnored(): void
    {
        DirectoryRemover::remove($this->tempDir . '/missing');
        self::assertDirectoryExists($this->tempDir);
    }

    public function testDoesNotFollowChildSymlinks(): void
    {
        $root = $this->tempDir . '/output';
        $target = $this->tempDir . '/preserved';
        mkdir($root);
        mkdir($target);
        file_put_contents($target . '/keep.txt', 'keep');
        $this->link($target, $root . '/directory-link');
        $this->link($target . '/keep.txt', $root . '/file-link');
        $this->link($target . '/missing', $root . '/dangling-link');
        $this->link($root, $root . '/cycle');

        DirectoryRemover::remove($root);

        self::assertDirectoryDoesNotExist($root);
        self::assertSame('keep', file_get_contents($target . '/keep.txt'));
    }

    public function testRemovesRootSymlinkWithoutDeletingItsTarget(): void
    {
        $target = $this->tempDir . '/preserved';
        $link = $this->tempDir . '/link';
        mkdir($target);
        file_put_contents($target . '/keep.txt', 'keep');
        $this->link($target, $link);

        DirectoryRemover::remove($link);

        self::assertFalse(is_link($link));
        self::assertSame('keep', file_get_contents($target . '/keep.txt'));
    }

    public function testRemovesDanglingRootSymlink(): void
    {
        $link = $this->tempDir . '/link';
        $this->link($this->tempDir . '/missing', $link);
        DirectoryRemover::remove($link);
        self::assertFalse(is_link($link));
    }

    private function link(string $target, string $link): void
    {
        if (!function_exists('symlink') || !@symlink($target, $link)) {
            self::markTestSkipped('Creating symlinks is not supported.');
        }
    }
}
