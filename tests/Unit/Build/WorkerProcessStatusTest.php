<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\WorkerProcessStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function function_exists;

final class WorkerProcessStatusTest extends TestCase
{
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
