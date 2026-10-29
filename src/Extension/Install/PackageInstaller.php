<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Lock\LockFile;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestReader;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use YiiPress\Extension\Transaction\Transaction;

use function array_map;
use function hash_file;
use function implode;
use function sprintf;

/**
 * Installs an extension from a GitHub release: selects the release, downloads and checks its archive, then moves
 * the package into place and updates `yiipress.lock` (and the activation file) in one transaction.
 *
 * No file of the package is included or executed.
 */
final readonly class PackageInstaller
{
    private const string ARCHIVE = 'archive.tar.gz';
    private const string ROOT = 'root';

    public function __construct(
        private GitHubReleaseSource $source,
        private InstallLimits $limits = new InstallLimits(),
        private TarGzReader $archiveReader = new TarGzReader(),
        private ManifestReader $manifestReader = new ManifestReader(),
        private EngineCompatibility $compatibility = new EngineCompatibility(),
        private Recovery $recovery = new Recovery(),
    ) {}

    /**
     * @throws InstallException With the code of the problem; project files stay unchanged.
     */
    public function install(InstallRequest $request, DestinationPolicyInterface $policy): InstallResult
    {
        $mutationLock = MutationLock::acquire($request->projectRoot);
        try {
            $recovered = $this->recovery->recover($request->projectRoot);
            $lockFile = LockFile::read($request->projectRoot . '/' . LockFile::FILE_NAME);
            $selected = new ReleaseSelector($this->source)->select(
                $request->repository,
                $request->type,
                $request->version,
                $request->engine,
                $request->platform,
            );

            $transaction = Transaction::begin($request->projectRoot);
            try {
                [$locked, $activation] = $this->stage($transaction, $request, $policy, $lockFile, $selected);
                $transaction->commit();
            } finally {
                $transaction->discard();
            }
        } finally {
            $mutationLock->release();
        }

        return new InstallResult($selected, $locked, $activation?->path, $recovered);
    }

    /**
     * Downloads, checks and records every change in the transaction; changes no project file.
     *
     * @return array{LockedExtension, FileChange|null}
     */
    private function stage(
        Transaction $transaction,
        InstallRequest $request,
        DestinationPolicyInterface $policy,
        LockFile $lockFile,
        SelectedRelease $selected,
    ): array {
        $staging = $transaction->stagingDirectory();
        $archive = $this->source->downloadArchive(
            $request->repository,
            $selected->release,
            $staging . '/' . self::ARCHIVE,
        );
        $extracted = $this->archiveReader->extract($archive->file, $staging . '/' . self::ROOT, $this->limits);
        $manifest = $this->readManifest($extracted->root, $request, $selected);

        $destination = $policy->destination($manifest, $lockFile, $request->projectRoot);
        $packageDir = $extracted->root . '/' . ManifestReader::PACKAGE_DIR;
        $files = DirectoryHashes::of($packageDir);
        $activation = $policy->activation($manifest, $request->projectRoot);

        $locked = new LockedExtension(
            type: $manifest->type,
            source: $request->repository->url(),
            version: $selected->release->version,
            tag: $selected->release->tag,
            commit: $extracted->commit,
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: $archive->url,
            archiveSha256: $archive->sha256,
            archiveSize: $archive->size,
            destination: $destination,
            name: $policy->localName($manifest),
            manifestSha256: (string) hash_file('sha256', $extracted->root . '/' . ManifestReader::FILE_NAME),
            files: $files,
        );

        $transaction->moveDirectory(
            $transaction->stagingPath(self::ROOT . '/' . ManifestReader::PACKAGE_DIR),
            $destination,
            $files,
        );
        $transaction->replaceFile(LockFile::FILE_NAME, $lockFile->withExtension($manifest->id, $locked)->toJson());
        if ($activation !== null) {
            $transaction->replaceFile($activation->path, $activation->contents);
        }

        return [$locked, $activation];
    }

    /**
     * Reads the manifest from the archive and checks it describes the selected release.
     */
    private function readManifest(string $root, InstallRequest $request, SelectedRelease $selected): ExtensionManifest
    {
        try {
            $manifest = $this->manifestReader->read($root, $request->type);
        } catch (InvalidManifestException $exception) {
            throw InstallException::fromInvalidManifest($exception);
        }

        $expected = $selected->manifest;
        if ((string) $manifest->id !== (string) $expected->id || $manifest->version !== $expected->version) {
            throw new InstallException('manifest_mismatch', sprintf(
                'The archive of %s %s holds %s %s, but its extension.yaml at the tag names %s %s.',
                $request->repository,
                $selected->release->tag,
                $manifest->id,
                $manifest->version,
                $expected->id,
                $expected->version,
            ));
        }
        ReleaseSelector::checkMatches($manifest, $selected->release, $request->type, $request->repository);

        $errors = $this->compatibility->check($manifest, $request->engine, $request->platform);
        if ($errors !== []) {
            throw new InstallException('release_incompatible', sprintf(
                'Release %s of %s cannot be installed: %s',
                $selected->release->tag,
                $request->repository,
                implode(' ', array_map(static fn(ManifestError $error): string => $error->message, $errors)),
            ));
        }

        return $manifest;
    }
}
