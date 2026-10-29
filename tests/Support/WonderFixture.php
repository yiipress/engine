<?php

declare(strict_types=1);

namespace YiiPress\Tests\Support;

use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Archive\ExtractedArchive;
use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Lock\LockedExtension;

use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function mkdir;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The theme yiipress/wonder 1.0.0 from `Data/extension/wonder-v1.0.0.tar.gz` (made by `git archive`), served as
 * the GitHub repository yiipress/theme-wonder, and the `yiipress.lock` entry an installation of it records.
 */
final class WonderFixture
{
    public const string ID = 'yiipress/wonder';
    public const string REPOSITORY = 'yiipress/theme-wonder';
    public const string ARCHIVE_URL = 'https://codeload.github.com/yiipress/theme-wonder/tar.gz/refs/tags/v1.0.0';

    public static function archive(): string
    {
        return (string) file_get_contents(__DIR__ . '/Data/extension/wonder-v1.0.0.tar.gz');
    }

    /**
     * Serves the release v1.0.0 with the given archive bytes, the fixture by default.
     */
    public static function http(?string $archive = null): FakeHttpClient
    {
        return new FakeHttpClient()->gitHub(self::REPOSITORY, [
            'v1.0.0' => ['manifest' => self::manifest(), 'archive' => $archive ?? self::archive()],
        ]);
    }

    public static function manifest(): string
    {
        return self::inspect(self::archive(), static fn(ExtractedArchive $extracted): string
            => (string) file_get_contents($extracted->root . '/extension.yaml'));
    }

    public static function commit(?string $archive = null): string
    {
        return self::inspect($archive ?? self::archive(), static fn(ExtractedArchive $extracted): string
            => $extracted->commit);
    }

    /**
     * The lock entry of an installation from the given archive bytes, the fixture by default.
     */
    public static function locked(?string $archive = null): LockedExtension
    {
        $archive ??= self::archive();

        return self::inspect($archive, static fn(ExtractedArchive $extracted): LockedExtension => new LockedExtension(
            type: ExtensionType::Theme,
            source: 'https://github.com/' . self::REPOSITORY,
            version: '1.0.0',
            tag: 'v1.0.0',
            commit: $extracted->commit,
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: self::ARCHIVE_URL,
            archiveSha256: hash('sha256', $archive),
            archiveSize: strlen($archive),
            destination: 'themes/wonder',
            name: 'wonder',
            manifestSha256: (string) hash_file('sha256', $extracted->root . '/extension.yaml'),
            files: DirectoryHashes::of($extracted->root . '/package'),
        ));
    }

    /**
     * @template T
     * @param callable(ExtractedArchive): T $read
     * @return T
     */
    private static function inspect(string $archive, callable $read): mixed
    {
        $directory = sys_get_temp_dir() . '/yiipress-wonder-fixture-' . uniqid();
        mkdir($directory, 0o755, true);
        try {
            file_put_contents($directory . '/archive.tar.gz', $archive);

            return $read(new TarGzReader()->extract(
                $directory . '/archive.tar.gz',
                $directory . '/root',
                new InstallLimits(),
            ));
        } finally {
            DirectoryRemover::remove($directory);
        }
    }
}
