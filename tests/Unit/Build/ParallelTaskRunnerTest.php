<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\ParallelTaskRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_unique;
use function count;
use function defined;
use function file;
use function file_put_contents;
use function function_exists;
use function getmypid;
use function is_file;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertSame;
use function posix_kill;
use function sprintf;
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

    public function testRunFailsWhenWorkerIsTerminatedBySignal(): void
    {
        $this->skipWhenSignalWorkerTestIsUnsupported();

        $runner = new ParallelTaskRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terminated by signal');

        $runner->run(
            [1, 2],
            2,
            static function (int $task): int {
                if ($task === 1) {
                    posix_kill(getmypid(), \SIGKILL);
                }

                return 1;
            },
            minTasksPerWorker: 1,
        );
    }

    private function skipWhenSignalWorkerTestIsUnsupported(): void
    {
        foreach (['pcntl_fork', 'pcntl_waitpid', 'pcntl_wifsignaled', 'pcntl_wtermsig', 'posix_kill'] as $function) {
            if (!function_exists($function)) {
                self::markTestSkipped(sprintf('%s() is required to test signaled worker failures.', $function));
            }
        }

        if (!defined('SIGKILL')) {
            self::markTestSkipped('SIGKILL is required to test signaled worker failures.');
        }
    }
}
