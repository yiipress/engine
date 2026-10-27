<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

final readonly class ThemeMetadata
{
    /**
     * @param list<string> $screenshots Paths relative to the package root.
     */
    public function __construct(
        public ThemeFallback $fallback = ThemeFallback::Minimal,
        public ?string $intendedUse = null,
        public array $screenshots = [],
        public ?string $demo = null,
    ) {}
}
