<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\ProjectThemeDiscovery;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ProjectThemeDiscoveryBench
{
    private string $rootDir;
    private ProjectThemeDiscovery $discovery;

    public function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/yiipress-project-theme-bench-' . uniqid();
        mkdir($this->rootDir . '/themes', 0o755, true);

        for ($i = 1; $i <= 50; $i++) {
            mkdir($this->rootDir . '/themes/theme-' . $i);
        }

        $this->discovery = new ProjectThemeDiscovery();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRegisterProjectThemes(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', '/built-in/minimal'));

        $this->discovery->register($registry, $this->rootDir . '/themes');
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
