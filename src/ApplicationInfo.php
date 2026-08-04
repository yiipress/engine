<?php

declare(strict_types=1);

namespace YiiPress;

use Composer\InstalledVersions;
final class ApplicationInfo
{
    public const string NAME = 'YiiPress';
    public const string COMMIT = '';
    public const string VERSION = 'unknown';

    public static function version(): string
    {
        $package = InstalledVersions::getRootPackage();
        $version = $package['name'] === 'yiipress/engine' ? $package['pretty_version'] : null;
        $reference = $package['name'] === 'yiipress/engine' ? $package['reference'] : null;

        return self::resolveVersion($version, $reference);
    }

    private static function resolveVersion(?string $version, ?string $reference): string
    {
        if (
            $version !== null
            && !str_ends_with($version, '+no-version-set')
            && !str_starts_with($version, 'dev-')
            && !str_ends_with($version, '-dev')
        ) {
            return $version;
        }

        return self::COMMIT !== '' ? self::COMMIT : ($reference ?? self::VERSION);
    }
}
