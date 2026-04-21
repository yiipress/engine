<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\LiveReload;

use App\Web\LiveReload\FileWatcher;
use App\Web\LiveReload\LiveReloadAction;
use App\Web\LiveReload\SiteBuildRunner;
use FilesystemIterator;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function pcntl_fork;
use function pcntl_waitpid;

final class LiveReloadActionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_live_reload_action_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testReturnsPingWhenNothingChanges(): void
    {
        file_put_contents($this->tempDir . '/page.md', '# Hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        $action = $this->createAction($watcher, 50);
        $response = $action();

        assertSame(200, $response->getStatusCode());
        assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        assertStringContainsString("retry: 1000\n", (string) $response->getBody());
        assertStringContainsString("event: ping\ndata: ok\n\n", (string) $response->getBody());
    }

    public function testReturnsReloadWhenFilesChangeDuringWait(): void
    {
        $file = $this->tempDir . '/page.md';
        file_put_contents($file, '# Hello');

        $watcher = new FileWatcher([$this->tempDir]);
        $watcher->hasChanges();

        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);

        if ($pid === 0) {
            usleep(100_000);
            file_put_contents($file, "# Updated\n");
            exit(0);
        }

        try {
            $action = $this->createAction($watcher, 1_000);
            $response = $action();
        } finally {
            pcntl_waitpid($pid, $status);
        }

        assertSame(200, $response->getStatusCode());
        assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        assertStringContainsString("retry: 1000\n", (string) $response->getBody());
        assertStringContainsString("event: reload\ndata: changed\n\n", (string) $response->getBody());
    }

    private function createAction(
        FileWatcher $fileWatcher,
        int $waitTimeoutMilliseconds,
    ): LiveReloadAction {
        return new LiveReloadAction(
            new ResponseFactory(),
            new StreamFactory(),
            $fileWatcher,
            new SiteBuildRunner('/bin/true', $this->tempDir, $this->tempDir . '/output'),
            $waitTimeoutMilliseconds,
        );
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
