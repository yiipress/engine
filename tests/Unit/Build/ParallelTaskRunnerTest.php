<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\ParallelTaskRunner;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

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
}
