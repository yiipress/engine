<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

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
use YiiPress\Build\SiteChecker;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class SiteCheckerBench
{
    private string $outputDir;
    private SiteChecker $checker;

    public function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-site-checker-bench-' . bin2hex(random_bytes(8));
        $this->createDir($this->outputDir);

        for ($i = 1; $i <= 100; $i++) {
            $dir = $this->outputDir . '/page-' . $i;
            $this->createDir($dir);
            $next = $i === 100 ? 1 : $i + 1;
            $this->writeFile(
                $dir . '/index.html',
                '<h1 id="top">Page ' . $i . '</h1>'
                . '<a href="../page-' . $next . '/#top">Next</a>'
                . '<a href="../page-1/">Home</a>'
                . '<img src="../assets/logo.svg">',
            );
        }

        $this->createDir($this->outputDir . '/assets');
        $this->writeFile($this->outputDir . '/assets/logo.svg', '<svg/>');

        $this->checker = new SiteChecker();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    #[Revs(20)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchInternalSiteCheck(): void
    {
        $this->checker->check($this->outputDir);
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
                $this->removeDirectory($item->getPathname());
            } else {
                $this->removeFile($item->getPathname());
            }
        }

        $this->removeDirectory($path);
    }

    private function createDir(string $path): void
    {
        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create benchmark directory: ' . $path);
        }
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Unable to write benchmark fixture: ' . $path);
        }
    }

    private function removeFile(string $path): void
    {
        if (!unlink($path)) {
            throw new RuntimeException('Unable to remove benchmark file: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!rmdir($path)) {
            throw new RuntimeException('Unable to remove benchmark directory: ' . $path);
        }
    }
}
