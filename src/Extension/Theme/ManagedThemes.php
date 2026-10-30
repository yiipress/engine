<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use YiiPress\Build\Theme;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ThemeFallback;

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
     * @return array<string, list<string>> Directory name under `themes/` that is a destination in `yiipress.lock` →
     *         the themes tried after it ({@see Theme::$fallback}): its declared fallback, then `local`.
     *
     * @throws InstallException `lock_invalid` or `lock_unsupported`.
     */
    public static function fallbacks(string $projectRoot): array
    {
        $fallbacks = [];
        foreach (LockFile::read($projectRoot . '/' . LockFile::FILE_NAME)->extensions() as $locked) {
            $destination = $locked->destination;
            $name = substr($destination, strlen(self::PREFIX));
            if (str_starts_with($destination, self::PREFIX) && $name !== '' && !str_contains($name, '/')) {
                $fallbacks[$name] = $locked->fallback === ThemeFallback::None
                    ? [Theme::LOCAL]
                    : [Theme::BUNDLED, Theme::LOCAL];
            }
        }

        return $fallbacks;
    }
}
