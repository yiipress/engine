<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\WorkerProcessStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function function_exists;
use function pcntl_fork;
use function sprintf;

final class WorkerProcessStatusTest extends TestCase
{
    public function testWaitForFailsWhenChildExitsWithNonZeroCode(): void
    {
        foreach (['pcntl_fork', 'pcntl_waitpid', 'pcntl_wifexited', 'pcntl_wexitstatus'] as $function) {
            if (!function_exists($function)) {
                self::markTestSkipped(sprintf('%s() is required to test non-zero worker exits.', $function));
            }
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            self::fail('Unable to fork test worker.');
        }

        if ($pid === 0) {
            exit(7);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exited with code 7');

        WorkerProcessStatus::waitFor($pid);
    }

    public function testWaitForFailsWhenPidIsNotAChildProcess(): void
    {
        if (!function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl_waitpid() is required to test worker wait failures.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to wait for worker process');

        WorkerProcessStatus::waitFor(999_999);
    }
}
