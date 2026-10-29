<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lock;

use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Manifest\ThemeFallback;
use YiiPress\Extension\PortablePath;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\SemanticVersion;

use function array_diff;
use function array_keys;
use function array_map;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Validates one entry of `extensions` in `yiipress.lock`.
 *
 * @internal
 */
final class LockedExtensionParser
{
    private const array KEYS = [
        'archive', 'commit', 'destination', 'fallback', 'files', 'manifest_sha256', 'name', 'source', 'tag', 'type',
        'version',
    ];
    private const array ARCHIVE_KEYS = ['kind', 'sha256', 'size', 'url'];
    private const string SHA256 = '/^[0-9a-f]{64}$/D';

    /**
     * @param array<array-key, mixed> $data
     */
    public static function parse(string $id, array $data): LockedExtension
    {
        $field = static fn(string $name): string => "extensions.$id.$name";
        self::rejectUnknown($data, self::KEYS, $field(''));

        $type = ExtensionType::tryFrom(self::string($data, 'type', $field('type')))
            ?? throw self::invalid($field('type'), 'is not a known extension type');
        $source = self::string($data, 'source', $field('source'));
        try {
            $normalised = GitHubRepository::fromUrl($source)->url();
        } catch (InstallException) {
            $normalised = null;
        }
        if ($normalised !== $source) {
            throw self::invalid($field('source'), 'must be https://github.com/<owner>/<repo>');
        }
        $version = self::string($data, 'version', $field('version'));
        if (!SemanticVersion::isValid($version)) {
            throw self::invalid($field('version'), 'must be a SemVer version');
        }
        $commit = self::matching($data, 'commit', '/^[0-9a-f]{40}$/D', $field('commit'));
        $destination = self::path(self::string($data, 'destination', $field('destination')), $field('destination'));
        $name = null;
        $fallback = null;
        if ($type === ExtensionType::Theme) {
            $name = self::matching($data, 'name', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $field('name'));
            $fallback = ThemeFallback::tryFrom(self::string($data, 'fallback', $field('fallback')))
                ?? throw self::invalid($field('fallback'), 'must be "minimal" or "none"');
        } else {
            foreach (['name', 'fallback'] as $themeOnly) {
                if (isset($data[$themeOnly])) {
                    throw self::invalid($field($themeOnly), 'is allowed for themes only');
                }
            }
        }

        $archive = $data['archive'] ?? null;
        if (!is_array($archive)) {
            throw self::invalid($field('archive'), 'must be an object');
        }
        self::rejectUnknown($archive, self::ARCHIVE_KEYS, $field('archive.'));
        $kind = self::string($archive, 'kind', $field('archive.kind'));
        if ($kind !== LockedExtension::ARCHIVE_GITHUB_TARBALL) {
            throw self::invalid($field('archive.kind'), 'must be "github-tarball"');
        }
        $size = $archive['size'] ?? null;
        if (!is_int($size) || $size < 0) {
            throw self::invalid($field('archive.size'), 'must be a non-negative integer');
        }

        return new LockedExtension(
            type: $type,
            source: $source,
            version: $version,
            tag: self::matching($data, 'tag', '/^v?\S+$/D', $field('tag')),
            commit: $commit,
            archiveKind: $kind,
            archiveUrl: self::matching($archive, 'url', '~^https://\S+$~D', $field('archive.url')),
            archiveSha256: self::matching($archive, 'sha256', self::SHA256, $field('archive.sha256')),
            archiveSize: $size,
            destination: $destination,
            name: $name,
            fallback: $fallback,
            manifestSha256: self::matching($data, 'manifest_sha256', self::SHA256, $field('manifest_sha256')),
            files: self::files($data['files'] ?? null, $field('files')),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function files(mixed $files, string $field): array
    {
        if (!is_array($files)) {
            throw self::invalid($field, 'must be an object');
        }
        $result = [];
        foreach ($files as $path => $hash) {
            $path = (string) $path;
            self::path($path, "$field.$path");
            if (!is_string($hash) || preg_match(self::SHA256, $hash) !== 1) {
                throw self::invalid("$field.$path", 'must be a SHA-256 hex digest');
            }
            $result[$path] = $hash;
        }

        return $result;
    }

    private static function path(string $path, string $field): string
    {
        $problem = PortablePath::problem($path);
        if ($problem !== null) {
            throw self::invalid($field, $problem);
        }

        return $path;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key, string $field): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw self::invalid($field, 'must be a non-empty string');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function matching(array $data, string $key, string $pattern, string $field): string
    {
        $value = self::string($data, $key, $field);
        if (preg_match($pattern, $value) !== 1) {
            throw self::invalid($field, 'has an invalid value');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $known
     */
    private static function rejectUnknown(array $data, array $known, string $prefix): void
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), $known);
        if ($unknown !== []) {
            throw self::invalid($prefix . implode(', ', $unknown), 'is not a known field');
        }
    }

    public static function invalid(string $field, string $problem): InstallException
    {
        return new InstallException('lock_invalid', sprintf('yiipress.lock: field "%s" %s.', $field, $problem));
    }
}
