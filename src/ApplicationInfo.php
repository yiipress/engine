<?php

declare(strict_types=1);

namespace App;

use Composer\InstalledVersions;

final class ApplicationInfo
{
    public const string NAME = 'YiiPress';
    public const string VERSION = '1.0.0';

    public static function version(): string
    {
        $version = InstalledVersions::getPrettyVersion('yiipress/engine');

        if ($version === null || str_ends_with($version, '+no-version-set')) {
            return self::VERSION;
        }

        return $version;
    }
}
