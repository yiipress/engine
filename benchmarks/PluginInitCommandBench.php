<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use YiiPress\Console\PluginInitCommand;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class PluginInitCommandBench
{
    private string $rootPath;

    public function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/yiipress-plugin-init-bench-' . uniqid();
        mkdir($this->rootPath . '/content', 0o755, true);
    }

    public function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->rootPath);
    }

    #[Revs(1)]
    #[Iterations(5)]
    public function benchScaffoldPlugin(): void
    {
        new CommandTester(new PluginInitCommand($this->rootPath))->execute(['name' => 'Badge Labels']);
    }
}
