<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;

use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Themes of a project that `get:theme` installed, read from `yiipress.lock`.
 */
final class ManagedThemes
{
    private const string PREFIX = ThemeDestinationPolicy::THEMES_DIR . '/';

    /**
     * @return list<string> Directory names under `themes/` that are a destination in `yiipress.lock`.
     *
     * @throws InstallException `lock_invalid` or `lock_unsupported`.
     */
    public static function directoryNames(string $projectRoot): array
    {
        $names = [];
        foreach (LockFile::read($projectRoot . '/' . LockFile::FILE_NAME)->destinations() as $destination) {
            $name = substr($destination, strlen(self::PREFIX));
            if (str_starts_with($destination, self::PREFIX) && $name !== '' && !str_contains($name, '/')) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
