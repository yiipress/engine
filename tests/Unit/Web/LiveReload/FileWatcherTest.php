<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\LiveReload;

use App\Web\LiveReload\FileWatcher;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

final class FileWatcherTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_watcher_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testFirstCallNeverReportsChanges(): void
    {
        file_put_contents($this->tempDir . '/test.md', 'hello');

        $watcher = new FileWatcher([$this->tempDir]);

        assertFalse($watcher->hasChanges());
    }

    public function testNoChangeWhenFilesUnmodified(): void
    {
        file_put_contents($this->tempDir . '/test.md', 'hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        assertFalse($watcher->hasChanges());
    }

    public function testDetectsFileModification(): void
    {
        $file = $this->tempDir . '/test.md';
        file_put_contents($file, 'hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        sleep(1);
        file_put_contents($file, 'world');
        clearstatcache();

        assertTrue($watcher->hasChanges());
    }

    public function testDetectsNewFile(): void
    {
        file_put_contents($this->tempDir . '/test.md', 'hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        sleep(1);
        file_put_contents($this->tempDir . '/new.md', 'new content');
        clearstatcache();

        assertTrue($watcher->hasChanges());
    }

    public function testDetectsFileRemoval(): void
    {
        $file = $this->tempDir . '/test.md';
        file_put_contents($file, 'hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        unlink($file);
        clearstatcache();

        assertTrue($watcher->hasChanges());
    }

    public function testHandlesNonExistentDirectory(): void
    {
        $watcher = new FileWatcher(['/nonexistent/path']);

        assertFalse($watcher->hasChanges());
        assertFalse($watcher->hasChanges());
    }

    public function testWatchesMultipleDirectories(): void
    {
        $dir2 = sys_get_temp_dir() . '/yiipress_watcher_test2_' . uniqid();
        mkdir($dir2, 0o755, true);

        file_put_contents($this->tempDir . '/a.md', 'a');
        file_put_contents($dir2 . '/b.md', 'b');

        $watcher = new FileWatcher([$this->tempDir, $dir2]);
        $watcher->hasChanges();

        sleep(1);
        file_put_contents($dir2 . '/b.md', 'changed');
        clearstatcache();

        assertTrue($watcher->hasChanges());

        $this->removeDir($dir2);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
