<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Release\SelectedRelease;

/**
 * What an installation did.
 */
final readonly class InstallResult
{
    /**
     * @param string|null $activated Project-relative path of the file that activates the extension, `null` when
     *        nothing was activated.
     * @param string|null $recovered What recovery of an earlier interrupted operation did, `null` when none was
     *        pending.
     */
    public function __construct(
        public SelectedRelease $selected,
        public LockedExtension $locked,
        public ?string $activated,
        public ?string $recovered,
    ) {}
}
