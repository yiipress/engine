<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lifecycle;

use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Restore\InstalledPackage;
use YiiPress\Extension\Theme\LockedThemeDestination;

use function implode;
use function sprintf;

/**
 * The lock entry of an installed theme whose files are exactly as installed, which update and removal require.
 */
final class InstalledTheme
{
    /**
     * The lock entry of an installed theme whose files are as installed.
     *
     * @param string $command The command to run again once the problem is fixed, for example `update:theme`.
     *
     * @throws InstallException `not_installed`, `extension_not_theme`, `lock_invalid` or `local_changes`.
     */
    public static function unchanged(
        string $projectRoot,
        LockFile $lock,
        ExtensionId $id,
        string $command,
    ): LockedExtension {
        $locked = self::find($lock, $id);
        self::checkUnchanged($projectRoot, (string) $id, $locked, $command);

        return $locked;
    }

    /**
     * The lock entry of an installed theme; its files are not checked.
     *
     * @throws InstallException `not_installed`, `extension_not_theme` or `lock_invalid`.
     */
    public static function find(LockFile $lock, ExtensionId $id): LockedExtension
    {
        $locked = $lock->get($id);
        if ($locked === null) {
            throw new InstallException(
                'not_installed',
                sprintf('Theme %s is not installed: yiipress.lock has no entry for it.', $id),
                solution: 'Check the theme ID in yiipress.lock, or install the theme with get:theme.',
            );
        }
        if ($locked->type !== ExtensionType::Theme) {
            throw new InstallException('extension_not_theme', sprintf(
                '%s is installed as a %s, not a theme.',
                $id,
                $locked->type->value,
            ));
        }
        $problem = LockedThemeDestination::problem((string) $id, $locked);
        if ($problem !== null) {
            throw $problem;
        }

        return $locked;
    }

    /**
     * Checks the files of a theme that is to be removed: as installed, or the whole directory gone.
     *
     * @return bool Whether the directory is there; `false` when nothing is left at the destination.
     *
     * @throws InstallException `local_changes`.
     */
    public static function unchangedOrGone(string $projectRoot, LockedExtension $locked, string $id): bool
    {
        $installed = InstalledPackage::inspect($projectRoot, $locked);
        if (!$installed->exists && $installed->collision === null) {
            return false;
        }
        self::check($installed, $id, $locked, 'remove:theme');

        return true;
    }

    /**
     * Added, changed and deleted files are local changes, which update and removal must not lose.
     */
    private static function checkUnchanged(
        string $projectRoot,
        string $id,
        LockedExtension $locked,
        string $command,
    ): void {
        self::check(InstalledPackage::inspect($projectRoot, $locked), $id, $locked, $command);
    }

    private static function check(
        InstalledPackage $installed,
        string $id,
        LockedExtension $locked,
        string $command,
    ): void {
        if ($installed->collision === null && $installed->isIntact()) {
            return;
        }
        $changes = [];
        if ($installed->collision !== null) {
            $changes[] = sprintf('"%s" takes its place', $installed->collision);
        } elseif (!$installed->exists) {
            $changes[] = 'the directory is missing';
        } else {
            if ($installed->modified !== []) {
                $changes[] = 'changed ' . implode(', ', $installed->modified);
            }
            if ($installed->unexpected !== []) {
                $changes[] = 'added ' . implode(', ', $installed->unexpected);
            }
            if ($installed->missing !== []) {
                $changes[] = 'deleted ' . implode(', ', $installed->missing);
            }
        }
        $solution = $installed->isRepairable()
            ? sprintf('Run restore:extensions to put the missing files back, then run %s again.', $command)
            : sprintf(
                'Move your changes out of "%s" (keep a copy), run restore:extensions, then run %s again.',
                $installed->collision ?? $locked->destination,
                $command,
            );

        throw new InstallException('local_changes', sprintf(
            'Theme %s %s in "%s" has local changes: %s.',
            $id,
            $locked->version,
            $locked->destination,
            implode('; ', $changes),
        ), solution: $solution);
    }
}
