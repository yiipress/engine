<?php

declare(strict_types=1);

namespace YiiPress\Build;

use function array_map;
use function array_slice;
use function ceil;
use function count;
use function function_exists;
use function intdiv;
use function max;
use function min;

final class ParallelTaskRunner
{
    private const int MIN_TASKS_PER_WORKER = 32;

    public function __construct(private ?PortableWorkerPool $workerPool = null) {}

    /**
     * Runs tasks sequentially, or in parallel across independent worker processes.
     * pcntl_fork() would duplicate the running PHAR's file descriptor, so forked workers
     * would share its read offset and race on it when autoloading a class for the first
     * time at once, corrupting the decompression. Spawning independent processes instead
     * gives each one its own file descriptor, so there's nothing to race on.
     *
     * @template T
     * @param list<T> $tasks
     * @param callable(T): int $taskRunner used to process a single task sequentially
     * @param callable(list<T>): WorkerJobInterface $jobFactory builds a serializable job
     *     for a chunk of tasks, run by an independent worker process
     */
    public function run(array $tasks, int $workerCount, callable $taskRunner, callable $jobFactory, int $minTasksPerWorker = self::MIN_TASKS_PER_WORKER): int
    {
        if ($tasks === []) {
            return 0;
        }

        $effectiveWorkerCount = $this->effectiveWorkerCount(count($tasks), $workerCount, $minTasksPerWorker);
        if ($effectiveWorkerCount <= 1) {
            return $this->runSequential($tasks, $taskRunner);
        }

        $chunks = $this->partitionTasks($tasks, $effectiveWorkerCount);
        $jobs = array_map($jobFactory, $chunks);

        return ($this->workerPool ?? new PortableWorkerPool())->run($jobs);
    }

    private function effectiveWorkerCount(int $taskCount, int $requestedWorkerCount, int $minTasksPerWorker): int
    {
        if (!function_exists('proc_open') || $requestedWorkerCount <= 1) {
            return 1;
        }

        $maxWorkersByTaskVolume = max(1, intdiv($taskCount, max(1, $minTasksPerWorker)));

        return min($requestedWorkerCount, $maxWorkersByTaskVolume);
    }

    /**
     * @template T
     * @param list<T> $tasks
     * @param callable(T): int $taskRunner
     */
    private function runSequential(array $tasks, callable $taskRunner): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            $count += $taskRunner($task);
        }

        return $count;
    }

    /**
     * @template T
     * @param list<T> $tasks
     * @return list<list<T>>
     */
    private function partitionTasks(array $tasks, int $workerCount): array
    {
        $chunkSize = (int) ceil(count($tasks) / $workerCount);
        $chunks = [];

        for ($offset = 0, $taskCount = count($tasks); $offset < $taskCount; $offset += $chunkSize) {
            $chunks[] = array_slice($tasks, $offset, $chunkSize);
        }

        return $chunks;
    }
}
