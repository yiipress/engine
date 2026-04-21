<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Web\LiveReload\FileWatcher;
use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class FileWatcherBench
{
    private string $tempDir;
    private FileWatcher $watcher;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-bench-file-watcher-' . uniqid();
        mkdir($this->tempDir . '/nested', 0o755, true);

        file_put_contents($this->tempDir . '/index.md', '# Index');
        file_put_contents($this->tempDir . '/nested/page.md', '# Page');
        file_put_contents($this->tempDir . '/nested/meta.yaml', "title: Page\n");

        $this->watcher = new FileWatcher([$this->tempDir]);
        $this->watcher->hasChanges();
    }

    public function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
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

        rmdir($this->tempDir);
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchSteadyStateHasChanges(): void
    {
        $this->watcher->hasChanges();
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchImmediateWaitForChanges(): void
    {
        $this->watcher->waitForChanges(0);
    }
}
