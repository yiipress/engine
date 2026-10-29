<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Extension\Install\InstallException;

use function is_dir;
use function mkdir;
use function sprintf;

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
