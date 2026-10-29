<?php

declare(strict_types=1);

namespace YiiPress\Extension\Lock;

use YiiPress\Extension\ExtensionType;

use function ksort;

use const SORT_STRING;

/**
 * One installed extension as recorded in `yiipress.lock`.
 */
final readonly class LockedExtension
{
    public const string ARCHIVE_GITHUB_TARBALL = 'github-tarball';

    /**
     * @param string $source Normalised `https://github.com/<owner>/<repo>`.
     * @param string $version Normalised version, without `v`.
     * @param string $tag Tag the version came from, exactly as on GitHub.
     * @param string $commit Commit from the archive, 40 hex characters.
     * @param string $destination Path relative to the project root, `/` separators.
     * @param string|null $name Local theme name; `null` for other types.
     * @param array<string, string> $files Path relative to `$destination` → SHA-256 of every regular file.
     */
    public function __construct(
        public ExtensionType $type,
        public string $source,
        public string $version,
        public string $tag,
        public string $commit,
        public string $archiveKind,
        public string $archiveUrl,
        public string $archiveSha256,
        public int $archiveSize,
        public string $destination,
        public ?string $name,
        public string $manifestSha256,
        public array $files,
    ) {}

    /**
     * @return array<string, mixed> Fields as in the lock file, keys sorted.
     */
    public function toArray(): array
    {
        $files = $this->files;
        ksort($files, SORT_STRING);
        $data = [
            'archive' => [
                'kind' => $this->archiveKind,
                'sha256' => $this->archiveSha256,
                'size' => $this->archiveSize,
                'url' => $this->archiveUrl,
            ],
            'commit' => $this->commit,
            'destination' => $this->destination,
            // An object even for file names such as `0`, which PHP turns into list keys.
            'files' => (object) $files,
            'manifest_sha256' => $this->manifestSha256,
            'name' => $this->name,
            'source' => $this->source,
            'tag' => $this->tag,
            'type' => $this->type->value,
            'version' => $this->version,
        ];
        if ($this->name === null) {
            unset($data['name']);
        }

        return $data;
    }
}
