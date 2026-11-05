<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Extension\Install\InstallException;

use function file_exists;
use function is_dir;
use function is_link;
use function mkdir;
use function realpath;
use function sprintf;
use function str_starts_with;

/**
 * Paths inside `.yiipress/`, the project directory that holds the state of extension commands.
 */
final readonly class StatePaths
{
    public const string DIRECTORY = '.yiipress';

    public function __construct(public string $projectRoot) {}

    public function directory(): string
    {
        return $this->projectRoot . '/' . self::DIRECTORY;
    }

    public function journal(): string
    {
        return $this->directory() . '/journal.json';
    }

    public function mutationLock(): string
    {
        return $this->directory() . '/mutation.lock';
    }

    /**
     * Project-relative path of a transaction's staging directory.
     */
    public function staging(string $tx): string
    {
        return self::DIRECTORY . '/staging/' . $tx;
    }

    /**
     * Project-relative path of a transaction's backup directory.
     */
    public function backup(string $tx): string
    {
        return self::DIRECTORY . '/backup/' . $tx;
    }

    /**
     * Refuses a `.yiipress/`, `staging/` or `backup/` that is a symbolic link or resolves outside the project:
     * extension commands write and delete there, and must never do it in another directory.
     *
     * @throws InstallException `filesystem_failed`.
     */
    public function assertSafe(): void
    {
        $root = realpath($this->projectRoot);
        foreach ([self::DIRECTORY, self::DIRECTORY . '/staging', self::DIRECTORY . '/backup'] as $relative) {
            $path = $this->absolute($relative);
            if (!is_link($path) && !file_exists($path)) {
                continue;
            }
            $resolved = realpath($path);
            if (
                is_link($path)
                || $root === false
                || $resolved === false
                || !str_starts_with($resolved, $root . '/')
            ) {
                throw new InstallException(
                    'filesystem_failed',
                    sprintf(
                        '"%s" is a symbolic link or lies outside the project; it must be a plain directory.',
                        $relative,
                    ),
                    solution: sprintf('Delete "%s" in the project and run the command again.', $relative),
                );
            }
        }
    }

    public function absolute(string $relative): string
    {
        return $this->projectRoot . '/' . $relative;
    }

    public static function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new InstallException('filesystem_failed', sprintf('Could not create "%s".', $path));
        }
    }
}
