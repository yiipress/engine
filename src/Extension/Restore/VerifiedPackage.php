<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

/**
 * The package directory of a downloaded archive whose contents equal what `yiipress.lock` records.
 */
final readonly class VerifiedPackage
{
    /**
     * @param string $directory Absolute path of `package/` in staging.
     * @param string|null $warning Set when the archive bytes differ from the lock while the commit and every file
     *        match: GitHub regenerated the tarball.
     */
    public function __construct(
        public string $directory,
        public ?string $warning = null,
    ) {}
}
