<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

final readonly class RestoreResult
{
    /**
     * @param list<RestoredPackage> $packages Every locked package, by ID.
     * @param string|null $recovered What recovery of an interrupted operation did, `null` when nothing was pending.
     */
    public function __construct(
        public array $packages,
        public ?string $recovered = null,
    ) {}
}
