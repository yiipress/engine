<?php

declare(strict_types=1);

namespace App\Build;

final class Asset
{
    public static function url(
        string $path,
        string $rootPath = '',
        ?AssetFingerprintManifest $assetManifest = null,
    ): string {
        $path = AssetFingerprintManifest::normalizePath($path);
        $resolved = $assetManifest?->resolve($path) ?? $path;

        if ($rootPath !== '' && $rootPath !== '/') {
            return $rootPath . $resolved;
        }

        if ($rootPath === '/') {
            return '/' . $resolved;
        }

        return $resolved;
    }
}
