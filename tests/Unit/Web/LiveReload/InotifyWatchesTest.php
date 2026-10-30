<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\LiveReload;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Web\LiveReload\InotifyWatches;

use function PHPUnit\Framework\assertSame;

final class InotifyWatchesTest extends TestCase
{
    private string $root;
    /** @var resource */
    private mixed $stream;

    protected function setUp(): void
    {
        if (!function_exists('inotify_init')) {
            self::markTestSkipped('The inotify extension is required.');
        }

        $this->root = sys_get_temp_dir() . '/yiipress-inotify-watches-' . uniqid();
        mkdir($this->root . '/content/blog', 0o755, true);
        $stream = inotify_init();
        self::assertIsResource($stream);
        stream_set_blocking($stream, false);
        $this->stream = $stream;
    }

    protected function tearDown(): void
    {
        if (!isset($this->root)) {
            return;
        }

        fclose($this->stream);
        $this->removeDirectory($this->root);
    }

    public function testDirectoriesListsRootsRecursivelyAndSkipsMissingRoots(): void
    {
        $watches = new InotifyWatches($this->stream, [$this->root . '/content', $this->root . '/themes']);

        assertSame([$this->root . '/content', $this->root . '/content/blog'], $watches->directories());
    }

    public function testUpdateWatchesDirectoryCreatedAfterStart(): void
    {
        $watches = new InotifyWatches($this->stream, [$this->root . '/content']);
        $watches->addAll();

        mkdir($this->root . '/content/guides/advanced', 0o755, true);
        $watches->update($this->readEvents());
        file_put_contents($this->root . '/content/guides/advanced/intro.md', 'new');

        assertSame(['intro.md'], $this->eventNames($this->readEvents()));
    }

    public function testUpdateIgnoresBatchWithoutDirectoryEvents(): void
    {
        $watches = new InotifyWatches($this->stream, [$this->root . '/content']);
        $watches->addAll();

        mkdir($this->root . '/content/guides');
        $this->readEvents();
        $watches->update([['wd' => 1, 'mask' => constant('IN_MODIFY'), 'cookie' => 0, 'name' => 'index.md']]);
        file_put_contents($this->root . '/content/guides/intro.md', 'new');

        assertSame([], $this->readEvents());
    }

    /**
     * @return list<array{wd: int, mask: int, cookie: int, name: string}>
     */
    private function readEvents(): array
    {
        $events = inotify_read($this->stream);

        /** @var list<array{wd: int, mask: int, cookie: int, name: string}> */
        return is_array($events) ? $events : [];
    }

    /**
     * @param list<array{wd: int, mask: int, cookie: int, name: string}> $events
     * @return list<string>
     */
    private function eventNames(array $events): array
    {
        return array_values(array_unique(array_column($events, 'name')));
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
