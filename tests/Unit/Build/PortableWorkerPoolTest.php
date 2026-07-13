<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\PortableWorkerPool;
use YiiPress\Tests\Support\CountingWorkerJob;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function count;
use function file;
use function is_file;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertSame;
use function sys_get_temp_dir;
use function unlink;

final class PortableWorkerPoolTest extends TestCase
{
    public function testRunsJobsInSeparateProcessesAndAggregatesResults(): void
    {
        $pidFile = sys_get_temp_dir() . '/yiipress-portable-worker-pids-' . bin2hex(random_bytes(8));
        $workerScript = dirname(__DIR__, 2) . '/Support/portable-worker.php';

        try {
            $pool = new PortableWorkerPool([PHP_BINARY, $workerScript]);
            $count = $pool->run([
                new CountingWorkerJob($pidFile, 2),
                new CountingWorkerJob($pidFile, 3),
            ]);

            $pids = file($pidFile, FILE_IGNORE_NEW_LINES);
            assertNotFalse($pids);
            assertSame(5, $count);
            assertSame(2, count(array_unique($pids)));
        } finally {
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
        }
    }
}
