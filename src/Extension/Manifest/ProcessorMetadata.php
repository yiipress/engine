<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

final readonly class ProcessorMetadata
{
    /**
     * @param list<ProcessorEntryPoint> $entryPoints
     * @param list<ProcessorExample> $examples
     */
    public function __construct(
        public array $entryPoints,
        public array $examples = [],
    ) {}
}
