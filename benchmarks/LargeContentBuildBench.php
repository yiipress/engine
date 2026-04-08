<?php

declare(strict_types=1);

namespace App\Benchmarks;

use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function escapeshellarg;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_dir;
use function is_file;
use function mkdir;
use function usleep;
use function sys_get_temp_dir;
use function unlink;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class LargeContentBuildBench
{
    private string $contentDir;
    private string $outputDir;
    private string $rootPath;
    private string $tempDir;
    private string $touchFile;

    public function setUp(): void
    {
        $this->rootPath = dirname(__DIR__);
        $sourceDataDir = __DIR__ . '/data/realistic-content';

        if (!is_dir($sourceDataDir)) {
            throw new RuntimeException('Realistic benchmark data not found. Run: make bench-generate-realistic');
        }

        $this->tempDir = sys_get_temp_dir() . '/yiipress-realistic-build-bench-' . uniqid();
        $this->contentDir = $this->tempDir . '/content';
        $this->outputDir = $this->tempDir . '/output';
        $this->touchFile = $this->contentDir . '/collection-1/2025-09-02-entry-610.md';

        $this->copyDir($sourceDataDir, $this->contentDir);
    }

    public function tearDown(): void
    {
        $manifestPath = $this->rootPath . '/runtime/cache/build-manifest-' . hash('xxh128', $this->outputDir) . '.json';
        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        $buildCacheDir = $this->rootPath . '/runtime/cache/build';
        if (is_dir($buildCacheDir)) {
            $this->removeDir($buildCacheDir);
        }

        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullRebuildSequential(): void
    {
        $this->runBuild('--workers=1 --no-cache');
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullRebuild4Workers(): void
    {
        $this->runBuild('--workers=4 --no-cache');
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[BeforeMethods('prepareIncrementalNoChanges')]
    public function benchIncrementalNoChangesSequential(): void
    {
        $this->runBuild('--workers=1');
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    #[BeforeMethods('prepareIncrementalSingleChangedEntry')]
    public function benchIncrementalSingleChangedEntrySequential(): void
    {
        $this->runBuild('--workers=1');
    }

    public function prepareIncrementalNoChanges(): void
    {
        $this->runBuild('--workers=1');
    }

    public function prepareIncrementalSingleChangedEntry(): void
    {
        $this->runBuild('--workers=1');
        file_put_contents($this->touchFile, file_get_contents($this->touchFile) . "\n");
    }

    private function runBuild(string $args): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($this->rootPath . '/yii')
            . ' build'
            . ' --content-dir=' . escapeshellarg($this->contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' '
            . $args
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("Realistic build benchmark command failed:\n" . implode("\n", $output));
        }
    }

    private function copyDir(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $relativePath = $iterator->getSubPathname();
            $targetPath = $target . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0o755, true) && !is_dir($targetPath)) {
                    throw new RuntimeException("Directory was not created: $targetPath");
                }
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
                throw new RuntimeException("Directory was not created: $targetDir");
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function removeDir(string $path): void
    {
        while (is_dir($path)) {
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

            if (@rmdir($path)) {
                return;
            }

            usleep(10_000);
        }
    }
}
