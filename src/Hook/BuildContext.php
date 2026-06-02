<?php

declare(strict_types=1);

namespace YiiPress\Hook;

final readonly class BuildContext
{
    public function __construct(
        public string $rootPath,
        public string $contentDir,
        public string $outputDir,
        public int $workerCount,
        public bool $noCache,
        public bool $includeDrafts,
        public bool $includeFuture,
        public bool $dryRun,
        public bool $noWrite,
    ) {}
}
