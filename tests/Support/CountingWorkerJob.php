<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Build\ExecutableWorkerJobInterface;

use function file_put_contents;
use function getmypid;
use function usleep;

final readonly class CountingWorkerJob implements ExecutableWorkerJobInterface
{
    public function __construct(private string $pidFile, private int $count, private int $delayMicroseconds = 0) {}

    public function run(): int
    {
        if ($this->delayMicroseconds > 0) {
            usleep($this->delayMicroseconds);
        }
        file_put_contents($this->pidFile, getmypid() . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $this->count;
    }
}
