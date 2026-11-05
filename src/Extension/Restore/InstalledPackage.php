<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

use YiiPress\Extension\Lock\LockedExtension;

use function array_diff_key;
use function array_keys;
use function basename;
use function dirname;
use function file_exists;
use function hash_file;
use function is_dir;
use function is_file;
use function is_link;
use function mb_strtolower;
use function scandir;
use function sort;

use const SORT_STRING;

/**
 * How the installed files of a locked package compare with the hashes in `yiipress.lock`. Reads only the
 * destination; links are never followed.
 */
final readonly class InstalledPackage
{
    /**
     * @param bool $exists Whether the destination directory exists.
     * @param list<string> $missing Locked files that are not installed, relative to the destination.
     * @param list<string> $modified Installed files whose contents differ from the lock.
     * @param list<string> $unexpected Files, links and other entries the lock does not list.
     * @param string|null $collision Project-relative path that occupies the destination: a file, a link or a
     *        name differing only in case.
     */
    private function __construct(
        public bool $exists,
        public array $missing,
        public array $modified = [],
        public array $unexpected = [],
        public ?string $collision = null,
    ) {}

    public static function inspect(string $projectRoot, LockedExtension $locked): self
    {
        $missingAll = array_keys($locked->files);
        sort($missingAll, SORT_STRING);
        $collision = self::collision($projectRoot, $locked->destination);
        if ($collision !== null) {
            return new self(false, $missingAll, collision: $collision);
        }
        $directory = $projectRoot . '/' . $locked->destination;
        if (!is_dir($directory)) {
            return new self(false, $missingAll);
        }

        $seen = [];
        $modified = [];
        $unexpected = [];
        self::scan($directory, '', $locked->files, $seen, $modified, $unexpected);
        $missing = array_keys(array_diff_key($locked->files, $seen));
        sort($missing, SORT_STRING);
        sort($modified, SORT_STRING);
        sort($unexpected, SORT_STRING);

        return new self(true, $missing, $modified, $unexpected);
    }

    public function isIntact(): bool
    {
        return $this->exists && $this->missing === [] && $this->modified === [] && $this->unexpected === [];
    }

    /**
     * Whether only missing files are wrong, which a restore repairs.
     */
    public function isRepairable(): bool
    {
        return $this->collision === null && $this->modified === [] && $this->unexpected === [];
    }

    private static function collision(string $projectRoot, string $destination): ?string
    {
        $path = $projectRoot . '/' . $destination;
        if (is_link($path) || (file_exists($path) && !is_dir($path))) {
            return $destination;
        }
        $name = basename($destination);
        $parent = dirname($destination);
        $entries = is_dir($projectRoot . '/' . $parent) ? @scandir($projectRoot . '/' . $parent) : false;
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry !== $name && mb_strtolower($entry) === mb_strtolower($name)) {
                return $parent . '/' . $entry;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $locked
     * @param array<string, true> $seen
     * @param list<string> $modified
     * @param list<string> $unexpected
     */
    private static function scan(
        string $directory,
        string $prefix,
        array $locked,
        array &$seen,
        array &$modified,
        array &$unexpected,
    ): void {
        $entries = @scandir($directory);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            $relative = $prefix . $entry;
            if (!is_link($path) && is_dir($path)) {
                self::scan($path, $relative . '/', $locked, $seen, $modified, $unexpected);
            } elseif (is_link($path) || !is_file($path) || !isset($locked[$relative])) {
                $unexpected[] = $relative;
            } else {
                $seen[$relative] = true;
                if (@hash_file('sha256', $path) !== $locked[$relative]) {
                    $modified[] = $relative;
                }
            }
        }
    }
}
