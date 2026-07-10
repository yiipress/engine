<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\PortableWorkerPool;
use YiiPress\Tests\Support\CountingWorkerJob;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

use function bin2hex;
use function dirname;
use function is_file;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

#[Revs(1)]
#[Iterations(5)]
#[Warmup(1)]
final class PortableWorkerPoolBench
{
    public function benchStartTwoWorkers(): void
    {
        $pidFile = sys_get_temp_dir() . '/yiipress-portable-worker-bench-' . bin2hex(random_bytes(8));
        $workerScript = dirname(__DIR__) . '/tests/Support/portable-worker.php';

        try {
            (new PortableWorkerPool([PHP_BINARY, $workerScript]))->run([
                new CountingWorkerJob($pidFile, 1),
                new CountingWorkerJob($pidFile, 1),
            ]);
        } finally {
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
        }
    }
}
