<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

use Composer\Semver\Semver;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestParser;
use YiiPress\Extension\Platform;
use YiiPress\Extension\SemanticVersion;

use function array_keys;
use function array_map;
use function array_slice;
use function count;
use function implode;
use function sprintf;
use function str_contains;

/**
 * Chooses the release to install: the requested version, or the highest stable release compatible with this
 * YiiPress and platform.
 */
final readonly class ReleaseSelector
{
    public function __construct(
        private GitHubReleaseSource $source,
        private ManifestParser $parser = new ManifestParser(),
        private EngineCompatibility $compatibility = new EngineCompatibility(),
    ) {}

    /**
     * @param string|null $version Requested `X.Y.Z` without `v`; `null` picks the highest compatible release.
     */
    public function select(
        GitHubRepository $repository,
        ExtensionType $type,
        ?string $version,
        EngineVersion $engine,
        Platform $platform,
    ): SelectedRelease {
        if ($engine->version === null) {
            throw new InstallException('engine_version_unknown', sprintf(
                'This YiiPress is %s, so the compatibility of releases cannot be checked.',
                $engine->describe(),
            ), solution: 'Use a YiiPress release or nightly build. In a source checkout, pass --engine-version=X.Y.Z.');
        }
        if ($version !== null) {
            $this->checkRequestedVersion($version);
        }

        $releases = $this->source->releases($repository);
        if ($releases === []) {
            throw new InstallException('release_none', sprintf(
                '%s has no stable release with a version tag such as v1.0.0.',
                $repository->url(),
            ));
        }
        $byVersion = $this->groupByVersion($releases);

        return $version === null
            ? $this->highestCompatible($repository, $type, $byVersion, $engine, $platform)
            : $this->requested($repository, $type, $byVersion, $version, $engine, $platform);
    }

    private function checkRequestedVersion(string $version): void
    {
        if (!SemanticVersion::isValid($version)) {
            throw new InstallException('release_not_found', sprintf(
                '--version="%s" is not a version in the form X.Y.Z (without "v").',
                $version,
            ));
        }
        if (str_contains($version, '-')) {
            throw new InstallException('release_prerelease', sprintf(
                'Version %s is a pre-release; only stable releases can be installed for now.',
                $version,
            ));
        }
    }

    /**
     * @param array<string, list<Release>> $byVersion
     */
    private function requested(
        GitHubRepository $repository,
        ExtensionType $type,
        array $byVersion,
        string $version,
        EngineVersion $engine,
        Platform $platform,
    ): SelectedRelease {
        $release = $this->single($repository, $byVersion[$version] ?? null)
            ?? throw new InstallException('release_not_found', sprintf(
                '%s has no stable release %s.',
                $repository->url(),
                $version,
            ));

        $manifest = $this->manifest($repository, $release, $type);
        $errors = $this->compatibility->check($manifest, $engine, $platform);
        if ($errors !== []) {
            throw new InstallException('release_incompatible', sprintf(
                'Release %s of %s cannot be installed: %s',
                $release->tag,
                $repository,
                implode(' ', array_map(static fn(ManifestError $error): string => $error->message, $errors)),
            ));
        }

        return new SelectedRelease($release, $manifest, 'the requested version');
    }

    /**
     * @param array<string, list<Release>> $byVersion
     */
    private function highestCompatible(
        GitHubRepository $repository,
        ExtensionType $type,
        array $byVersion,
        EngineVersion $engine,
        Platform $platform,
    ): SelectedRelease {
        /** @var list<string> $versions */
        $versions = Semver::rsort(array_keys($byVersion));
        $reasons = [];
        foreach (array_slice($versions, 0, InstallLimits::CANDIDATE_MANIFESTS) as $version) {
            $release = $this->single($repository, $byVersion[$version]);
            if ($release === null) {
                continue;
            }
            try {
                $manifest = $this->manifest($repository, $release, $type);
            } catch (InstallException $exception) {
                $reasons[] = sprintf('%s: %s', $release->tag, $exception->getMessage());
                continue;
            }
            $errors = $this->compatibility->check($manifest, $engine, $platform);
            if ($errors === []) {
                return new SelectedRelease($release, $manifest, sprintf(
                    'highest stable release compatible with YiiPress %s',
                    $engine->describe(),
                ));
            }
            $reasons[] = sprintf('%s: %s', $release->tag, $errors[0]->message);
        }

        throw new InstallException('no_compatible_release', sprintf(
            "No release of %s is compatible with YiiPress %s. Checked %d of %d:\n- %s",
            $repository,
            $engine->describe(),
            count($reasons),
            count($versions),
            implode("\n- ", $reasons),
        ));
    }

    /**
     * @param list<Release>|null $releases
     */
    private function single(GitHubRepository $repository, ?array $releases): ?Release
    {
        if ($releases === null) {
            return null;
        }
        if (count($releases) > 1) {
            throw new InstallException('release_ambiguous', sprintf(
                'Releases %s of %s are the same version %s; delete or rename one of the tags.',
                implode(' and ', array_map(static fn(Release $release): string => $release->tag, $releases)),
                $repository,
                $releases[0]->version,
            ));
        }

        return $releases[0];
    }

    private function manifest(GitHubRepository $repository, Release $release, ExtensionType $type): ExtensionManifest
    {
        $yaml = $this->source->manifest($repository, $release)
            ?? throw new InstallException('manifest_invalid', sprintf(
                'Release %s of %s has no extension.yaml in the repository root.',
                $release->tag,
                $repository,
            ));
        try {
            $manifest = $this->parser->parse($yaml);
        } catch (InvalidManifestException $exception) {
            throw InstallException::fromInvalidManifest($exception);
        }

        return self::checkMatches($manifest, $release, $type, $repository);
    }

    /**
     * Checks that a manifest describes the release it came from.
     *
     * @throws InstallException `manifest_invalid` for another type, `manifest_mismatch` for another version.
     */
    public static function checkMatches(
        ExtensionManifest $manifest,
        Release $release,
        ExtensionType $type,
        GitHubRepository $repository,
    ): ExtensionManifest {
        if ($manifest->type !== $type) {
            throw new InstallException('manifest_invalid', sprintf(
                'Release %s of %s is a %s, not a %s.',
                $release->tag,
                $repository,
                $manifest->type->value,
                $type->value,
            ));
        }
        if ($manifest->version !== $release->version) {
            throw new InstallException('manifest_mismatch', sprintf(
                'Release %s of %s declares version %s in extension.yaml; it must be %s.',
                $release->tag,
                $repository,
                $manifest->version,
                $release->version,
            ));
        }

        return $manifest;
    }

    /**
     * @param list<Release> $releases
     * @return array<string, list<Release>>
     */
    private function groupByVersion(array $releases): array
    {
        $byVersion = [];
        foreach ($releases as $release) {
            $byVersion[$release->version][] = $release;
        }

        return $byVersion;
    }
}
