<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Release\SelectedRelease;

final readonly class UpdateResult
{
    /**
     * @param LockedExtension $previous Lock entry before the update.
     * @param LockedExtension $current Lock entry after it; the same object when nothing was changed.
     * @param string|null $recovered What recovery of an interrupted operation did first, if anything.
     */
    public function __construct(
        public LockedExtension $previous,
        public LockedExtension $current,
        public SelectedRelease $selected,
        public ?string $recovered,
    ) {}

    public function updated(): bool
    {
        return $this->current !== $this->previous;
    }
}
