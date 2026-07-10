<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Build\ExecutableWorkerJobInterface;

use function file_put_contents;
use function getmypid;

final readonly class CountingWorkerJob implements ExecutableWorkerJobInterface
{
    public function __construct(private string $pidFile, private int $count) {}

    public function run(): int
    {
        file_put_contents($this->pidFile, getmypid() . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $this->count;
    }
}
