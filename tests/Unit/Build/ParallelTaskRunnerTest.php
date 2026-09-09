<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\ParallelTaskRunner;
use YiiPress\Build\PortableWorkerPool;
use YiiPress\Build\WorkerJobInterface;
use YiiPress\Tests\Support\SummingWorkerJob;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

use function array_unique;
use function count;
use function defined;
use function dirname;
use function file;
use function function_exists;
use function is_file;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertSame;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ParallelTaskRunnerTest extends TestCase
{
    public function testRunProcessesTasksSequentiallyWhenSingleWorker(): void
    {
        $runner = new ParallelTaskRunner();

        $count = $runner->run(
            [1, 2, 3],
            1,
            static fn(int $task): int => $task,
            $this->failingJobFactory(),
        );

        assertSame(6, $count);
    }

    public function testRunProcessesTasksInParallelAndAggregatesCounts(): void
    {
        $runner = new ParallelTaskRunner($this->createTestWorkerPool());

        $count = $runner->run(
            [1, 2, 3, 4],
            2,
            static fn(int $task): int => $task,
            static fn(array $chunk): WorkerJobInterface => new SummingWorkerJob($chunk),
            minTasksPerWorker: 1,
        );

        assertSame(10, $count);
    }

    public function testRunUsesCustomMinimumTasksPerWorker(): void
    {
        $runner = new ParallelTaskRunner($this->createTestWorkerPool());
        $pidFile = sys_get_temp_dir() . '/yiipress-parallel-runner-pids-' . uniqid() . '.txt';

        try {
            $count = $runner->run(
                [1, 2, 3, 4],
                2,
                static fn(int $task): int => $task,
                static fn(array $chunk): WorkerJobInterface => new SummingWorkerJob($chunk, pidFile: $pidFile),
                minTasksPerWorker: 1,
            );

            $pids = file($pidFile, FILE_IGNORE_NEW_LINES);
            assertNotFalse($pids);

            assertSame(10, $count);
            assertSame(2, count(array_unique($pids)));
        } finally {
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
        }
    }

    /** @return iterable<string, array{int, int}> */
    public static function taskVolumes(): iterable
    {
        yield 'below two worker threshold' => [255, 0];
        yield 'two workers' => [256, 2];
        yield 'four workers' => [512, 4];
    }

    #[DataProvider('taskVolumes')]
    public function testDefaultMinimumTaskVolume(int $taskCount, int $expectedWorkers): void
    {
        $runner = new ParallelTaskRunner($this->createTestWorkerPool());
        $pidFile = sys_get_temp_dir() . '/yiipress-task-volume-' . uniqid() . '.txt';
        $tasks = array_fill(0, $taskCount, 1);

        try {
            $result = $runner->run(
                $tasks,
                4,
                static fn(int $task): int => $task,
                $expectedWorkers === 0
                    ? $this->failingJobFactory()
                    : static fn(array $chunk): WorkerJobInterface => new SummingWorkerJob($chunk, pidFile: $pidFile),
            );

            assertSame($taskCount, $result);
            if ($expectedWorkers > 0) {
                $pids = file($pidFile, FILE_IGNORE_NEW_LINES);
                assertNotFalse($pids);
                assertSame($expectedWorkers, count(array_unique($pids)));
            }
        } finally {
            if (is_file($pidFile)) {
                unlink($pidFile);
            }
        }
    }

    public function testRunFailsWhenWorkerIsTerminatedBySignal(): void
    {
        $this->skipWhenSignalWorkerTestIsUnsupported();

        $runner = new ParallelTaskRunner($this->createTestWorkerPool());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker process failed');

        $runner->run(
            [1, 2],
            2,
            static fn(int $task): int => $task,
            static fn(array $chunk): WorkerJobInterface => new SummingWorkerJob($chunk, killOnTask: 1),
            minTasksPerWorker: 1,
        );
    }

    private function skipWhenSignalWorkerTestIsUnsupported(): void
    {
        foreach (['proc_open', 'posix_kill'] as $function) {
            if (!function_exists($function)) {
                self::markTestSkipped(sprintf('%s() is required to test signaled worker failures.', $function));
            }
        }

        if (!defined('SIGKILL')) {
            self::markTestSkipped('SIGKILL is required to test signaled worker failures.');
        }
    }

    private function createTestWorkerPool(): PortableWorkerPool
    {
        return new PortableWorkerPool([PHP_BINARY, dirname(__DIR__, 2) . '/Support/portable-worker.php']);
    }

    /** @return callable(list<int>): WorkerJobInterface */
    private function failingJobFactory(): callable
    {
        return static function (array $chunk): never {
            throw new RuntimeException('The sequential path must not build a job.');
        };
    }
}
