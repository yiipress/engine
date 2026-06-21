<?php

declare(strict_types=1);

namespace YiiPress\Build;

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
            return UrlResolver::sitePath($resolved, $rootPath);
        }

        if ($rootPath === '/') {
            return '/' . $resolved;
        }

        return $resolved;
    }

    public static function themeUrl(
        string $path,
        string $themeName,
        string $rootPath = '',
        ?AssetFingerprintManifest $assetManifest = null,
    ): string {
        if ($themeName === '') {
            return self::url(ThemeAssetCopier::legacyLogicalPath($path), $rootPath, $assetManifest);
        }

        return self::url(ThemeAssetCopier::logicalPath($themeName, $path), $rootPath, $assetManifest);
    }
}
