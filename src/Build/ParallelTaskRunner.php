<?php

declare(strict_types=1);

namespace App\Build;

use RuntimeException;

use function array_slice;
use function ceil;
use function count;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function min;
use function pcntl_fork;
use function pcntl_wexitstatus;
use function pcntl_waitpid;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class ParallelTaskRunner
{
    private const int MIN_TASKS_PER_WORKER = 32;

    /**
     * @template T
     * @param list<T> $tasks
     * @param callable(T): int $taskRunner
     */
    public function run(array $tasks, int $workerCount, callable $taskRunner): int
    {
        if ($tasks === []) {
            return 0;
        }

        $effectiveWorkerCount = $this->effectiveWorkerCount(count($tasks), $workerCount);
        if ($effectiveWorkerCount <= 1) {
            return $this->runSequential($tasks, $taskRunner);
        }

        $chunks = $this->partitionTasks($tasks, $effectiveWorkerCount);
        $tempDir = sys_get_temp_dir() . '/yiipress_parallel_task_runner_' . uniqid();
        if (!mkdir($tempDir, 0o755, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }

        $files = [];
        $pids = [];

        try {
            foreach ($chunks as $index => $chunk) {
                $resultFile = $tempDir . '/' . $index . '.count';
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new RuntimeException('Failed to fork worker process');
                }

                if ($pid === 0) {
                    $count = $this->runSequential($chunk, $taskRunner);
                    file_put_contents($resultFile, (string) $count);
                    exit(0);
                }

                $files[] = $resultFile;
                $pids[] = $pid;
            }

            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                if (pcntl_wexitstatus($status) !== 0) {
                    throw new RuntimeException('One or more worker processes failed');
                }
            }

            $count = 0;
            foreach ($files as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    throw new RuntimeException(sprintf('Unable to read worker result file "%s".', $file));
                }
                $count += (int) $contents;
            }

            return $count;
        } finally {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    private function effectiveWorkerCount(int $taskCount, int $requestedWorkerCount): int
    {
        if ($requestedWorkerCount <= 1) {
            return 1;
        }

        $maxWorkersByTaskVolume = max(1, intdiv($taskCount, self::MIN_TASKS_PER_WORKER));

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
