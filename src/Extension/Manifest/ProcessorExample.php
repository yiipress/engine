<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

/**
 * Sample input and output shown in the catalog. Paths are relative to the package root.
 */
final readonly class ProcessorExample
{
    public function __construct(
        public string $input,
        public string $output,
    ) {}
}
