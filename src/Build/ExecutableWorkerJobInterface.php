<?php

declare(strict_types=1);

namespace YiiPress\Build;

interface ExecutableWorkerJobInterface extends WorkerJobInterface
{
    public function run(): int;
}
