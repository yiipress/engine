<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeAssetCopier;
use YiiPress\Build\ThemeRegistry;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ThemeAssetCopierBench
{
    private string $rootDir;
    private ThemeRegistry $registry;
    private ThemeAssetCopier $copier;

    public function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/yiipress-theme-assets-bench-' . uniqid();
        mkdir($this->rootDir . '/output', 0o755, true);

        $this->registry = new ThemeRegistry();
        for ($theme = 1; $theme <= 5; $theme++) {
            $themeDir = $this->rootDir . '/theme-' . $theme;
            mkdir($themeDir . '/assets/fonts', 0o755, true);
            for ($asset = 1; $asset <= 10; $asset++) {
                file_put_contents($themeDir . '/assets/asset-' . $asset . '.css', '.a{color:red}');
                file_put_contents($themeDir . '/assets/fonts/font-' . $asset . '.woff2', 'font');
            }
            $this->registry->register(new Theme('theme-' . $theme, $themeDir));
        }

        $this->copier = new ThemeAssetCopier();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    #[Revs(20)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchMappings(): void
    {
        $this->copier->mappings($this->registry);
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
