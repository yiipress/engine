<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Build\ExecutableWorkerJobInterface;

use function file_put_contents;
use function getmypid;
use function posix_kill;

final readonly class SummingWorkerJob implements ExecutableWorkerJobInterface
{
    /** @param list<int> $tasks */
    public function __construct(
        private array $tasks,
        private ?string $pidFile = null,
        private ?int $killOnTask = null,
    ) {}

    public function run(): int
    {
        if ($this->pidFile !== null) {
            file_put_contents($this->pidFile, getmypid() . \PHP_EOL, \FILE_APPEND | \LOCK_EX);
        }

        $sum = 0;
        foreach ($this->tasks as $task) {
            if ($this->killOnTask !== null && $task === $this->killOnTask) {
                posix_kill(getmypid(), \SIGKILL);
            }

            $sum += $task;
        }

        return $sum;
    }
}
