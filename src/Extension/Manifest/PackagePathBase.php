<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use function is_file;
use function realpath;
use function sprintf;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Checks that a manifest path is a regular file whose real path stays inside a base directory,
 * so a symlink cannot lead outside the package.
 *
 * @internal
 */
final readonly class PackagePathBase
{
    /**
     * @param string $directory Base directory on disk.
     * @param string $label Name of the base in messages, for example «package/».
     */
    public function __construct(
        private string $directory,
        private string $label,
    ) {}

    public function check(string $path, string $field): ?ManifestError
    {
        $base = realpath($this->directory);
        if ($base === false) {
            // A missing base directory is reported once by the reader.
            return null;
        }

        $real = realpath($this->directory . DIRECTORY_SEPARATOR . $path);
        if ($real !== false && !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return new ManifestError('path_outside', $field, sprintf(
                '%s: file "%s" resolves to a location outside %s.',
                $field,
                $path,
                $this->label,
            ));
        }
        if ($real === false || !is_file($real)) {
            return new ManifestError('path_not_found', $field, sprintf(
                '%s: file "%s" does not exist in %s.',
                $field,
                $path,
                $this->label,
            ));
        }

        return null;
    }
}
