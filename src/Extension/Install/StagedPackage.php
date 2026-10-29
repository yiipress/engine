<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Release\DownloadedArchive;
use YiiPress\Extension\Release\SelectedRelease;

/**
 * A downloaded and checked release, unpacked in the staging directory of a transaction.
 */
final readonly class StagedPackage
{
    /**
     * @param string $path Project-relative staging path of the package directory, for
     *        {@see \YiiPress\Extension\Transaction\Transaction::moveDirectory()}.
     * @param array<string, string> $files Files of the package directory → SHA-256.
     */
    public function __construct(
        public ExtensionManifest $manifest,
        public SelectedRelease $selected,
        public string $source,
        public DownloadedArchive $archive,
        public string $commit,
        public string $manifestSha256,
        public string $path,
        public array $files,
    ) {}

    /**
     * The lock entry of the package once it is at the destination.
     */
    public function locked(string $destination, ?string $name): LockedExtension
    {
        return new LockedExtension(
            type: $this->manifest->type,
            source: $this->source,
            version: $this->selected->release->version,
            tag: $this->selected->release->tag,
            commit: $this->commit,
            archiveKind: LockedExtension::ARCHIVE_GITHUB_TARBALL,
            archiveUrl: $this->archive->url,
            archiveSha256: $this->archive->sha256,
            archiveSize: $this->archive->size,
            destination: $destination,
            name: $name,
            manifestSha256: $this->manifestSha256,
            files: $this->files,
        );
    }
}
