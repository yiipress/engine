<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;
use YiiPress\Extension\Theme\UsedThemes;

use function is_dir;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * The used-themes scan a build runs when an installed theme is not the site theme, on the 10k-entry data.
 */
#[BeforeMethods('setUp')]
final class UsedThemesBench
{
    private string $dataDir;
    private string $cacheFile;

    public function setUp(): void
    {
        $this->dataDir = __DIR__ . '/data/content';
        if (!is_dir($this->dataDir)) {
            throw new RuntimeException('Benchmark data not found. Run: make bench-generate');
        }
        $this->cacheFile = sys_get_temp_dir() . '/yiipress-used-themes-bench-' . uniqid() . '.json';
    }

    #[Revs(1)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchFindWithoutCache(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        new UsedThemes($this->cacheFile)->find(['wonder'], $this->dataDir);
    }

    #[Revs(1)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchFindUnchangedContent(): void
    {
        new UsedThemes($this->cacheFile)->find(['wonder'], $this->dataDir);
    }
}
