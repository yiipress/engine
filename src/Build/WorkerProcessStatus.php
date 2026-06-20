<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function function_exists;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function pcntl_wifsignaled;
use function pcntl_wtermsig;
use function sprintf;

final class WorkerProcessStatus
{
    public static function waitFor(int $pid): void
    {
        if (!function_exists('pcntl_waitpid')) {
            throw new RuntimeException(sprintf('Unable to wait for worker process %d: pcntl_waitpid() is unavailable.', $pid));
        }

        $status = 0;
        $waitedPid = pcntl_waitpid($pid, $status);
        if ($waitedPid === -1) {
            throw new RuntimeException(sprintf('Failed to wait for worker process %d.', $pid));
        }

        if ($waitedPid !== $pid) {
            throw new RuntimeException(sprintf('Unexpected wait result for worker process %d: %d.', $pid, $waitedPid));
        }

        self::assertSucceeded($pid, $status);
    }

    public static function assertSucceeded(int $pid, int $status): void
    {
        if (function_exists('pcntl_wifexited') && pcntl_wifexited($status)) {
            if (!function_exists('pcntl_wexitstatus')) {
                throw new RuntimeException(sprintf('Worker process %d exited, but exit code is unavailable.', $pid));
            }

            $exitCode = pcntl_wexitstatus($status);
            if ($exitCode === 0) {
                return;
            }

            throw new RuntimeException(sprintf('Worker process %d exited with code %d.', $pid, $exitCode));
        }

        if (function_exists('pcntl_wifsignaled') && pcntl_wifsignaled($status)) {
            if (!function_exists('pcntl_wtermsig')) {
                throw new RuntimeException(sprintf('Worker process %d was terminated by a signal.', $pid));
            }

            throw new RuntimeException(sprintf(
                'Worker process %d was terminated by signal %d.',
                $pid,
                pcntl_wtermsig($status),
            ));
        }

        throw new RuntimeException(sprintf('Worker process %d did not exit normally.', $pid));
    }
}
