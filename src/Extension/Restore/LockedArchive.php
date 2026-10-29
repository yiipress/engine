<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockedExtensionParser;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestReader;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Release\Release;

use function array_diff_assoc;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function hash_file;
use function implode;
use function sort;
use function sprintf;
use function str_starts_with;
use function substr;

use const SORT_STRING;

/**
 * Downloads the exact archive a `yiipress.lock` entry records and checks it holds the locked package: the same
 * commit, manifest, ID, version and file hashes, compatible with this YiiPress. It never looks for another release.
 */
final readonly class LockedArchive
{
    private const string ARCHIVE = 'archive.tar.gz';
    private const string ROOT = 'root';

    public function __construct(
        private GitHubReleaseSource $source,
        private InstallLimits $limits = new InstallLimits(),
        private TarGzReader $archiveReader = new TarGzReader(),
        private ManifestReader $manifestReader = new ManifestReader(),
        private EngineCompatibility $compatibility = new EngineCompatibility(),
    ) {}

    /**
     * @param string $id Extension ID the entry is locked under.
     * @param string $stagingDir Empty directory to download and extract into.
     *
     * @throws InstallException `lock_invalid`, `release_not_found`, `archive_mismatch`, `manifest_invalid`,
     *         `release_incompatible`, archive and network errors.
     */
    public function fetch(
        string $id,
        LockedExtension $locked,
        string $stagingDir,
        EngineVersion $engine,
        Platform $platform,
    ): VerifiedPackage {
        $repository = GitHubRepository::fromUrl($locked->source);
        $release = $this->release($id, $locked, $repository);
        try {
            $archive = $this->source->downloadArchive($repository, $release, $stagingDir . '/' . self::ARCHIVE);
        } catch (InstallException $exception) {
            if ($exception->errorCode !== 'release_not_found') {
                throw $exception;
            }
            throw new InstallException('release_not_found', sprintf(
                'The archive of %s %s that yiipress.lock records for %s is no longer on GitHub.',
                $repository,
                $locked->tag,
                $id,
            ), solution: 'restore:extensions installs only the locked release. Ask the author to restore the tag, '
                . 'or install another release of the extension.', previous: $exception);
        }
        $extracted = $this->archiveReader->extract($archive->file, $stagingDir . '/' . self::ROOT, $this->limits);
        $describe = sprintf('The archive of %s %s', $repository, $locked->tag);
        if ($extracted->commit !== $locked->commit) {
            throw self::mismatch($id, sprintf(
                '%s is commit %s, but yiipress.lock records commit %s; the tag was moved.',
                $describe,
                $extracted->commit,
                $locked->commit,
            ));
        }
        if (hash_file('sha256', $extracted->root . '/' . ManifestReader::FILE_NAME) !== $locked->manifestSha256) {
            throw self::mismatch($id, sprintf(
                '%s holds an extension.yaml that differs from the locked one.',
                $describe,
            ));
        }
        $this->checkManifest($id, $locked, $extracted->root, $describe, $engine, $platform);

        $directory = $extracted->root . '/' . ManifestReader::PACKAGE_DIR;
        $files = DirectoryHashes::of($directory);
        $different = array_unique(array_merge(
            array_keys(array_diff_assoc($files, $locked->files)),
            array_keys(array_diff_assoc($locked->files, $files)),
        ));
        if ($different !== []) {
            sort($different, SORT_STRING);
            throw self::mismatch($id, sprintf(
                '%s holds package files that differ from yiipress.lock: %s.',
                $describe,
                implode(', ', $different),
            ));
        }

        $warning = null;
        if ($archive->sha256 !== $locked->archiveSha256 || $archive->size !== $locked->archiveSize) {
            $warning = sprintf(
                '%s has SHA-256 %s (%d bytes), not %s (%d bytes) as in yiipress.lock, but its commit and every file '
                . 'match; GitHub regenerated the tarball. The lock was not changed.',
                $describe,
                $archive->sha256,
                $archive->size,
                $locked->archiveSha256,
                $locked->archiveSize,
            );
        }

        return new VerifiedPackage($directory, $warning);
    }

    /**
     * The release the entry names; its archive URL must be the tag archive of its source.
     */
    private function release(string $id, LockedExtension $locked, GitHubRepository $repository): Release
    {
        $tag = $locked->tag;
        if ((str_starts_with($tag, 'v') ? substr($tag, 1) : $tag) !== $locked->version) {
            throw LockedExtensionParser::invalid(
                "extensions.$id.tag",
                sprintf('"%s" does not name version %s', $tag, $locked->version),
            );
        }
        $release = new Release($tag, $locked->version);
        if ($this->source->archiveUrl($repository, $release) !== $locked->archiveUrl) {
            throw LockedExtensionParser::invalid(
                "extensions.$id.archive.url",
                sprintf('is not the archive of %s %s', $repository, $tag),
            );
        }

        return $release;
    }

    private function checkManifest(
        string $id,
        LockedExtension $locked,
        string $root,
        string $describe,
        EngineVersion $engine,
        Platform $platform,
    ): void {
        try {
            $manifest = $this->manifestReader->read($root, $locked->type);
        } catch (InvalidManifestException $exception) {
            throw InstallException::fromInvalidManifest($exception);
        }
        if ((string) $manifest->id !== $id || $manifest->version !== $locked->version) {
            throw self::mismatch($id, sprintf(
                '%s holds %s %s, but yiipress.lock records %s %s.',
                $describe,
                $manifest->id,
                $manifest->version,
                $id,
                $locked->version,
            ));
        }
        $errors = $this->compatibility->check($manifest, $engine, $platform);
        if ($errors !== []) {
            throw new InstallException('release_incompatible', sprintf(
                '%s %s cannot be restored: %s',
                $id,
                $locked->version,
                implode(' ', array_map(static fn(ManifestError $error): string => $error->message, $errors)),
            ), solution: 'Use a YiiPress version the locked release supports, or update the extension.');
        }
    }

    private static function mismatch(string $id, string $message): InstallException
    {
        return new InstallException(
            'archive_mismatch',
            $message,
            solution: sprintf(
                'restore:extensions installs only the code locked for %s. Check the release with its author.',
                $id,
            ),
        );
    }
}
