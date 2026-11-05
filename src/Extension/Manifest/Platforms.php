<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

/**
 * Operating systems and architectures a package supports. An empty list means any.
 */
final readonly class Platforms
{
    /**
     * @param list<string> $os Values among `linux`, `macos`, `windows`.
     * @param list<string> $arch Values among `amd64`, `arm64`.
     */
    public function __construct(
        public array $os = [],
        public array $arch = [],
    ) {}
}
