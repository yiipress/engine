<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\DirectoryRemover;
use Yiisoft\Files\FileHelper;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
#[Revs(1)]
#[Iterations(5)]
final class DirectoryRemoverBench
{
    private string $directory;

    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yiipress-removal-bench-' . uniqid();
        for ($i = 0; $i < 10000; ++$i) {
            $page = $this->directory . '/collection-' . ($i % 3) . '/entry-' . $i;
            mkdir($page, 0o755, true);
            file_put_contents($page . '/index.html', '<p>Page</p>');
        }
    }

    public function tearDown(): void
    {
        DirectoryRemover::remove($this->directory);
    }

    public function benchRemovePreviousBuildWithFileHelper(): void
    {
        FileHelper::removeDirectory($this->directory);
    }

    public function benchRemovePreviousBuild(): void
    {
        DirectoryRemover::remove($this->directory);
    }
}
