<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ExtensionManifest;

/**
 * Type-specific part of updating and removing an installed extension.
 *
 * @internal Used only by {@see ExtensionUpdater} and {@see ExtensionRemover}, which pick the rules of a type.
 */
interface LifecycleRulesInterface
{
    public function type(): ExtensionType;

    /**
     * Why the lock entry of an extension of this type cannot be trusted, `null` when it can.
     */
    public function destinationProblem(string $id, LockedExtension $locked): ?InstallException;

    /**
     * @throws InstallException When the project still uses the extension, so it must not be removed.
     */
    public function checkUnused(string $projectRoot, LockedExtension $locked): void;

    /**
     * Called before any file changes, only when the update replaces the installed release.
     *
     * @throws InstallException When the release would break how the project uses the extension.
     */
    public function checkUpdate(string $projectRoot, LockedExtension $previous, ExtensionManifest $next): void;
}
