<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

use YiiPress\Extension\Install\InstallException;

use function array_slice;
use function count;
use function explode;
use function implode;
use function mb_strtolower;
use function sprintf;

/**
 * Remembers the paths of an archive and refuses ones that clash on a case-insensitive file system.
 *
 * @internal
 */
final class ArchivePathIndex
{
    /** @var array<string, array{path: string, directory: bool}> Keyed by lowercase path. */
    private array $paths = [];

    public function add(string $path, bool $directory): void
    {
        $segments = explode('/', $path);
        for ($i = 1, $count = count($segments); $i < $count; $i++) {
            $this->remember(implode('/', array_slice($segments, 0, $i)), true, $path);
        }
        $this->remember($path, $directory, $path);
    }

    private function remember(string $path, bool $directory, string $entry): void
    {
        $key = mb_strtolower($path);
        $known = $this->paths[$key] ?? null;
        if ($known === null) {
            $this->paths[$key] = ['path' => $path, 'directory' => $directory];
            return;
        }
        if ($known['path'] === $path && $known['directory'] && $directory) {
            return;
        }

        throw new InstallException('archive_path_collision', sprintf(
            'The release archive entry "%s" clashes with "%s"; paths must differ by more than letter case '
            . 'and a file cannot share a path with another file or a directory.',
            $entry,
            $known['path'],
        ));
    }
}
