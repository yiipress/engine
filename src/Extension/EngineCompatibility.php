<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use Composer\Semver\Semver;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\ManifestError;

use function implode;
use function in_array;
use function sprintf;

/**
 * Checks whether a package can run on this YiiPress: the `yiipress` constraint and `platforms`.
 */
final class EngineCompatibility
{
    /**
     * @param Platform|null $platform Local platform; the running one when `null`.
     * @return list<ManifestError> Empty when the package is compatible.
     */
    public function check(ExtensionManifest $manifest, EngineVersion $engine, ?Platform $platform = null): array
    {
        $platform ??= Platform::current();

        $errors = [];
        $versionError = $this->versionError($manifest, $engine);
        if ($versionError !== null) {
            $errors[] = $versionError;
        }
        foreach (
            [
                'platforms.os' => [$manifest->platforms->os, $platform->os],
                'platforms.arch' => [$manifest->platforms->arch, $platform->arch],
            ] as $field => [$supported, $local]
        ) {
            $platformError = $this->platformError($manifest, $field, $supported, $local);
            if ($platformError !== null) {
                $errors[] = $platformError;
            }
        }

        return $errors;
    }

    private function versionError(ExtensionManifest $manifest, EngineVersion $engine): ?ManifestError
    {
        $package = sprintf('%s %s supports YiiPress %s', $manifest->name, $manifest->version, $manifest->yiipress);
        if ($engine->version === null) {
            return new ManifestError('engine_version_unknown', 'yiipress', sprintf(
                '%s; this YiiPress is %s, so compatibility cannot be checked.',
                $package,
                $engine->describe(),
            ));
        }
        if (Semver::satisfies($engine->version, $manifest->yiipress)) {
            return null;
        }

        return new ManifestError('engine_incompatible', 'yiipress', sprintf(
            '%s; this is YiiPress %s.',
            $package,
            $engine->describe(),
        ));
    }

    /**
     * @param list<string> $supported Empty means any.
     */
    private function platformError(ExtensionManifest $manifest, string $field, array $supported, ?string $local): ?ManifestError
    {
        if ($supported === [] || ($local !== null && in_array($local, $supported, true))) {
            return null;
        }

        return new ManifestError('platform_unsupported', $field, sprintf(
            '%s %s supports %s; this system is %s.',
            $manifest->name,
            $manifest->version,
            implode(', ', $supported),
            $local ?? 'not one of them',
        ));
    }
}
