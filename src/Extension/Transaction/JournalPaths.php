<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Extension\PortablePath;

use function dirname;
use function file_exists;
use function is_link;
use function preg_match;
use function realpath;
use function str_starts_with;

/**
 * Which paths a transaction may name, so neither an installation nor recovery of a tampered journal touches
 * other files or anything outside the project.
 */
final class JournalPaths
{
    /** A theme directory; later extension types add their own. */
    private const string DIRECTORY_PATTERN = '~^themes/[a-z0-9]+(?:-[a-z0-9]+)*$~D';
    /** A file inside a theme directory. */
    private const string PACKAGE_FILE_PATTERN = '~^themes/[a-z0-9]+(?:-[a-z0-9]+)*/.+$~sD';
    /** `yiipress.lock` in the root, or `config.yaml` in the root or a content directory. */
    private const string FILE_PATTERN = '~^(?:yiipress\.lock|(?:[^/]+/)*config\.yaml)$~D';

    public static function isPortable(string $path): bool
    {
        return PortablePath::problem($path) === null;
    }

    public static function isDirectoryTarget(string $path): bool
    {
        return self::isPortable($path) && preg_match(self::DIRECTORY_PATTERN, $path) === 1;
    }

    /**
     * A file inside a theme directory, which a restore puts back when it is missing.
     */
    public static function isPackageFileTarget(string $path): bool
    {
        return self::isPortable($path) && preg_match(self::PACKAGE_FILE_PATTERN, $path) === 1;
    }

    public static function isFileTarget(string $path): bool
    {
        return self::isPortable($path) && preg_match(self::FILE_PATTERN, $path) === 1;
    }

    /**
     * Whether a path lies in the staging or backup directory of the transaction.
     */
    public static function isTransactionPath(string $path, string $tx, StatePaths $paths): bool
    {
        return self::isPortable($path)
            && (str_starts_with($path, $paths->staging($tx) . '/') || str_starts_with($path, $paths->backup($tx) . '/'));
    }

    /**
     * Whether the path, after resolving symbolic links in its existing parents, stays inside the project.
     * The last segment itself is not resolved: replacing or deleting a link changes the link only.
     */
    public static function staysInside(string $path, StatePaths $paths): bool
    {
        $root = realpath($paths->projectRoot);
        if ($root === false) {
            return false;
        }
        $parent = dirname($paths->absolute($path));
        while (!file_exists($parent) && !is_link($parent)) {
            $parent = dirname($parent);
        }
        $resolved = realpath($parent);

        return $resolved !== false && ($resolved === $root || str_starts_with($resolved, $root . '/'));
    }
}
