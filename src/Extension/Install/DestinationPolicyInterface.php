<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;

/**
 * Type-specific part of an installation: where the package goes and how it is activated.
 */
interface DestinationPolicyInterface
{
    /**
     * Local name recorded in the lock file, `null` for types without one.
     */
    public function localName(ExtensionManifest $manifest): ?string;

    /**
     * @return string Destination relative to the project root, `/` separators. It does not exist yet.
     *
     * @throws InstallException When the package cannot go there.
     */
    public function destination(ExtensionManifest $manifest, LockFile $lock, string $projectRoot): string;

    /**
     * @return FileChange|null The change that activates the package, `null` when nothing is activated.
     *
     * @throws InstallException When activation is asked for but cannot be done safely.
     */
    public function activation(ExtensionManifest $manifest, string $projectRoot): ?FileChange;
}
