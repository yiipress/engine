<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\Lock\LockedExtension;

final readonly class RemoveResult
{
    /**
     * @param LockedExtension $removed Lock entry of the removed theme.
     * @param bool $deletedDirectory Whether the directory was deleted; `false` when it was already gone.
     * @param string|null $recovered What recovery of an interrupted operation did first, if anything.
     */
    public function __construct(
        public LockedExtension $removed,
        public bool $deletedDirectory,
        public ?string $recovered,
    ) {}
}
