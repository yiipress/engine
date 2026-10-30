<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

final readonly class ProcessorEntryPoint
{
    /**
     * @param string $path Path relative to `package/`.
     * @param list<ProcessorStage> $stages
     */
    public function __construct(
        public string $path,
        public array $stages,
    ) {}
}
