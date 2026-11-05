<?php

declare(strict_types=1);

namespace YiiPress\Extension\Theme;

use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockedExtensionParser;
use YiiPress\Extension\Transaction\JournalPaths;

use function sprintf;

/**
 * A theme belongs in `themes/<name>`; a lock entry that points anywhere else is not trusted.
 */
final class LockedThemeDestination
{
    public static function problem(string $id, LockedExtension $locked): ?InstallException
    {
        $expected = ThemeDestinationPolicy::THEMES_DIR . '/' . $locked->name;
        if ($locked->destination !== $expected || !JournalPaths::isDirectoryTarget($expected)) {
            return LockedExtensionParser::invalid(
                "extensions.$id.destination",
                sprintf('must be "%s" for theme "%s"', $expected, $locked->name),
            );
        }

        return null;
    }
}
