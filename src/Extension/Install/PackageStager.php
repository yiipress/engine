<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Archive\InstallLimits;
use YiiPress\Extension\Archive\TarGzReader;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\InvalidManifestException;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\ManifestReader;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Extension\Transaction\Transaction;

use function array_map;
use function hash_file;
use function implode;
use function sprintf;

/**
 * Downloads the archive of a selected release into the staging directory of a transaction, unpacks it and checks
 * that it holds that release and that the release is compatible. Changes no project file.
 *
 * No file of the package is included or executed.
 */
final readonly class PackageStager
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
     * @throws InstallException With the code of the problem.
     */
    public function stage(Transaction $transaction, InstallRequest $request, SelectedRelease $selected): StagedPackage
    {
        $staging = $transaction->stagingDirectory();
        $archive = $this->source->downloadArchive(
            $request->repository,
            $selected->release,
            $staging . '/' . self::ARCHIVE,
        );
        $extracted = $this->archiveReader->extract($archive->file, $staging . '/' . self::ROOT, $this->limits);
        $manifest = $this->readManifest($extracted->root, $request, $selected);

        return new StagedPackage(
            manifest: $manifest,
            selected: $selected,
            source: $request->repository->url(),
            archive: $archive,
            commit: $extracted->commit,
            manifestSha256: (string) hash_file('sha256', $extracted->root . '/' . ManifestReader::FILE_NAME),
            path: $transaction->stagingPath(self::ROOT . '/' . ManifestReader::PACKAGE_DIR),
            files: DirectoryHashes::of($extracted->root . '/' . ManifestReader::PACKAGE_DIR),
        );
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
