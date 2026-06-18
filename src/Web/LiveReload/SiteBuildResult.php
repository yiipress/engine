<?php

declare(strict_types=1);

namespace YiiPress\Web\LiveReload;

final readonly class SiteBuildResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
