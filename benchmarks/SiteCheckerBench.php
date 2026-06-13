<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\SiteChecker;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class SiteCheckerBench
{
    private string $outputDir;
    private SiteChecker $checker;

    public function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-site-checker-bench-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        for ($i = 1; $i <= 100; $i++) {
            $dir = $this->outputDir . '/page-' . $i;
            mkdir($dir, 0o755, true);
            $next = $i === 100 ? 1 : $i + 1;
            file_put_contents(
                $dir . '/index.html',
                '<h1 id="top">Page ' . $i . '</h1>'
                . '<a href="../page-' . $next . '/#top">Next</a>'
                . '<a href="../page-1/">Home</a>'
                . '<img src="../assets/logo.svg">',
            );
        }

        mkdir($this->outputDir . '/assets', 0o755, true);
        file_put_contents($this->outputDir . '/assets/logo.svg', '<svg/>');

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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
