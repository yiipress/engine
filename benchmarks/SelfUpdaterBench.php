<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Update\Package;
use YiiPress\Update\PackageLocator;
use YiiPress\Update\ReleaseClient;
use YiiPress\Update\SelfUpdater;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class SelfUpdaterBench
{
    private string $directory;
    private Package $package;
    private SelfUpdater $updater;

    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yiipress-self-update-bench-' . uniqid('', true);
        mkdir($this->directory . '/releases/latest/download', recursive: true);
        $contents = str_repeat('YiiPress package contents', 10_000);
        file_put_contents($this->directory . '/releases/latest/download/yiipress.phar', $contents);
        file_put_contents(
            $this->directory . '/releases/latest/download/SHA256SUMS',
            hash('sha256', $contents) . "  yiipress.phar\n",
        );
        $target = $this->directory . '/yiipress.phar';
        file_put_contents($target, 'old');
        $this->package = new Package($target, 'yiipress.phar');
        $this->updater = new SelfUpdater(
            new PackageLocator(),
            new ReleaseClient('file://' . $this->directory . '/releases'),
        );
    }

    public function tearDown(): void
    {
        unlink($this->directory . '/releases/latest/download/yiipress.phar');
        unlink($this->directory . '/releases/latest/download/SHA256SUMS');
        unlink($this->directory . '/yiipress.phar');
        rmdir($this->directory . '/releases/latest/download');
        rmdir($this->directory . '/releases/latest');
        rmdir($this->directory . '/releases');
        rmdir($this->directory);
    }

    #[Revs(10)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchVerifyAndReplacePackage(): void
    {
        $this->updater->update(package: $this->package);
    }
}
