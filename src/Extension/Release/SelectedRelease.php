<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

use YiiPress\Extension\Manifest\ExtensionManifest;

/**
 * The release chosen for installation, with the manifest it was chosen by.
 */
final readonly class SelectedRelease
{
    /**
     * @param string $reason Why it was chosen, for example «highest stable release compatible with YiiPress 0.1.13».
     */
    public function __construct(
        public Release $release,
        public ExtensionManifest $manifest,
        public string $reason,
    ) {}
}
