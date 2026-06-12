<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function function_exists;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function pcntl_wifsignaled;
use function pcntl_wtermsig;
use function sprintf;

final class WorkerProcessStatus
{
    public static function assertSucceeded(int $pid, int $status): void
    {
        if (function_exists('pcntl_wifexited') && pcntl_wifexited($status)) {
            $exitCode = pcntl_wexitstatus($status);
            if ($exitCode === 0) {
                return;
            }

            throw new RuntimeException(sprintf('Worker process %d exited with code %d.', $pid, $exitCode));
        }

        if (function_exists('pcntl_wifsignaled') && pcntl_wifsignaled($status)) {
            throw new RuntimeException(sprintf(
                'Worker process %d was terminated by signal %d.',
                $pid,
                pcntl_wtermsig($status),
            ));
        }

        throw new RuntimeException(sprintf('Worker process %d did not exit normally.', $pid));
    }
}
