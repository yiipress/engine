<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Extension\Install\InstallException;

use function hash_file;
use function ksort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

use const SORT_STRING;

/**
 * SHA-256 of every regular file under a directory, keyed by `/`-separated relative path.
 */
final class DirectoryHashes
{
    /**
     * @return array<string, string> Sorted by path.
     *
     * @throws InstallException `filesystem_failed` when a file cannot be read or is a link.
     */
    public static function of(string $directory): array
    {
        $hashes = [];
        $prefix = strlen($directory) + 1;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->isLink() || !$file->isFile()) {
                throw new InstallException('filesystem_failed', sprintf('"%s" is not a regular file.', $path));
            }
            $hash = @hash_file('sha256', $path);
            if ($hash === false) {
                throw new InstallException('filesystem_failed', sprintf('Could not read "%s".', $path));
            }
            $hashes[str_replace('\\', '/', substr($path, $prefix))] = $hash;
        }
        ksort($hashes, SORT_STRING);

        return $hashes;
    }
}
