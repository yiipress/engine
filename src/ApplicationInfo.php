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
        $version = InstalledVersions::getPrettyVersion('yiipress/engine');

        if ($version !== null && !str_ends_with($version, '+no-version-set') && !str_starts_with($version, 'dev-')) {
            return $version;
        }

        return self::COMMIT !== '' ? self::COMMIT : (InstalledVersions::getReference('yiipress/engine') ?? self::VERSION);
    }
}
