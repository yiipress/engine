<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\ParallelTaskRunner;
use PHPUnit\Framework\TestCase;

use function array_unique;
use function count;
use function file;
use function file_put_contents;
use function getmypid;
use function is_file;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertSame;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ParallelTaskRunnerTest extends TestCase
{
    public function testRunProcessesTasksSequentiallyWhenSingleWorker(): void
    {
        $runner = new ParallelTaskRunner();

        $count = $runner->run([1, 2, 3], 1, static fn (int $task): int => $task);

        assertSame(6, $count);
    }

    public function testRunProcessesTasksInParallelAndAggregatesCounts(): void
    {
        $runner = new ParallelTaskRunner();

        $count = $runner->run([1, 2, 3, 4], 2, static fn (int $task): int => $task);

        assertSame(10, $count);
    }

    public function testRunUsesCustomMinimumTasksPerWorker(): void
    {
        $runner = new ParallelTaskRunner();
        $pidFile = sys_get_temp_dir() . '/yiipress-parallel-runner-pids-' . uniqid() . '.txt';

        try {
            $count = $runner->run(
                [1, 2, 3, 4],
                2,
                static function () use ($pidFile): int {
                    file_put_contents($pidFile, getmypid() . "\n", FILE_APPEND | LOCK_EX);

                    return 1;
                },
                minTasksPerWorker: 1,
            );

            $pids = file($pidFile, FILE_IGNORE_NEW_LINES);
            assertNotFalse($pids);

            assertSame(4, $count);
            assertSame(2, count(array_unique($pids)));
        } finally {
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
        }
    }
}
