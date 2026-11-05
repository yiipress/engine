<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use RuntimeException;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\Install\InstallException;

use function array_reverse;
use function bin2hex;
use function dirname;
use function file_exists;
use function file_get_contents;
use function hash_file;
use function is_dir;
use function is_file;
use function is_link;
use function random_bytes;
use function scandir;
use function sprintf;
use function unlink;

/**
 * Finishes or undoes an extension transaction that was interrupted.
 */
final class Recovery
{
    public const string INTERRUPTED_MESSAGE = 'An extension operation was interrupted. Run `yiipress recover:extensions`.';

    public static function pending(string $projectRoot): bool
    {
        return is_file(new StatePaths($projectRoot)->journal());
    }

    /**
     * The caller holds the {@see MutationLock}.
     *
     * @return string|null What was done, `null` when nothing was pending.
     *
     * @throws InstallException `recovery_failed`; the journal and backups are kept.
     */
    public function recover(string $projectRoot): ?string
    {
        $paths = new StatePaths($projectRoot);
        $paths->assertSafe();
        if (!is_file($paths->journal())) {
            return self::removeOrphans($paths)
                ? 'Temporary files of an interrupted extension operation were removed.'
                : null;
        }
        $journal = Journal::read($paths);
        if ($journal->state === Journal::COMMITTED) {
            self::cleanUp($paths, $journal->tx);
            $result = sprintf('The interrupted %s had completed; its temporary files were removed.', $journal->operation);
        } else {
            $this->undo($paths, $journal);
            $result = sprintf('The interrupted %s was undone.', $journal->operation);
        }
        self::removeOrphans($paths);

        return $result;
    }

    /**
     * Deletes staging and backup directories that no journal names, left by a process killed before it wrote
     * its journal. The caller holds the {@see MutationLock}, so no other operation is using them.
     *
     * @return bool Whether anything was found.
     */
    private static function removeOrphans(StatePaths $paths): bool
    {
        $found = false;
        foreach ([dirname($paths->staging('-')), dirname($paths->backup('-'))] as $parent) {
            $entries = @scandir($paths->absolute($parent));
            foreach ($entries === false ? [] : $entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $found = true;
                    self::removeQuietly($paths->absolute($parent . '/' . $entry));
                }
            }
        }

        return $found;
    }

    /**
     * Undoes the steps of a prepared transaction in reverse order, then removes its files and journal.
     */
    public function undo(StatePaths $paths, Journal $journal): void
    {
        foreach ($journal->steps as $step) {
            if (!JournalPaths::staysInside(Journal::target($step), $paths)) {
                throw self::failed(sprintf('"%s" resolves to a location outside the project.', Journal::target($step)));
            }
        }
        $notRetired = self::notRetired($paths, $journal);
        foreach (array_reverse($journal->steps) as $step) {
            if ($step['kind'] === 'move_dir' && isset($notRetired[$step['to']])) {
                // The directory there is the one the operation was going to retire, so the move never happened.
                continue;
            }
            if ($step['kind'] === 'move_dir') {
                $this->undoMove($paths, $journal->tx, $step['to'], $step['hashes']);
                continue;
            }
            if ($step['kind'] === 'add_file') {
                self::undoAdd($paths, $step['to'], $step['hash']);
                continue;
            }
            if ($step['kind'] === 'retire_dir') {
                self::undoRetire($paths, $step['from'], $step['backup']);
                continue;
            }
            $target = $paths->absolute($step['path']);
            if ($step['backup'] === null) {
                if ((file_exists($target) || is_link($target)) && !@unlink($target)) {
                    throw self::failed(sprintf('Could not delete "%s".', $step['path']));
                }
                continue;
            }
            $contents = @file_get_contents($paths->absolute($step['backup']));
            if ($contents === false) {
                throw self::failed(sprintf('The backup of "%s" is missing.', $step['path']));
            }
            AtomicFile::write($target, $contents);
        }
        self::cleanUp($paths, $journal->tx);
    }

    /**
     * @return array<string, true> Directories of `retire_dir` steps that were not applied: their backup is absent.
     */
    private static function notRetired(StatePaths $paths, Journal $journal): array
    {
        $notRetired = [];
        foreach ($journal->steps as $step) {
            if ($step['kind'] === 'retire_dir') {
                $backup = $paths->absolute($step['backup']);
                if (!file_exists($backup) && !is_link($backup)) {
                    $notRetired[$step['from']] = true;
                }
            }
        }

        return $notRetired;
    }

    public static function cleanUp(StatePaths $paths, string $tx): void
    {
        // The journal goes first: leftovers in staging or backup never block a later command.
        if (is_file($paths->journal()) && !@unlink($paths->journal())) {
            throw new InstallException('filesystem_failed', sprintf('Could not delete "%s".', $paths->journal()));
        }
        self::removeQuietly($paths->absolute($paths->staging($tx)));
        self::removeQuietly($paths->absolute($paths->backup($tx)));
    }

    private static function undoAdd(StatePaths $paths, string $to, string $hash): void
    {
        $target = $paths->absolute($to);
        if (!file_exists($target) && !is_link($target)) {
            return;
        }
        if (is_link($target) || !is_file($target) || @hash_file('sha256', $target) !== $hash) {
            throw self::failed(sprintf(
                '"%s" differs from what the interrupted operation added, so it was not deleted.',
                $to,
            ));
        }
        if (!@unlink($target)) {
            throw self::failed(sprintf('Could not delete "%s".', $to));
        }
    }

    /**
     * Moves a retired directory back, unless it was never moved or something else took its place.
     */
    private static function undoRetire(StatePaths $paths, string $from, string $backup): void
    {
        $source = $paths->absolute($backup);
        if (!file_exists($source) && !is_link($source)) {
            return;
        }
        $target = $paths->absolute($from);
        if (file_exists($target) || is_link($target)) {
            throw self::failed(sprintf(
                '"%s" appeared where the interrupted operation took the installed package from; '
                . 'the package is in "%s".',
                $from,
                $backup,
            ));
        }
        StatePaths::makeDirectory(dirname($target));
        if (!@rename($source, $target)) {
            throw self::failed(sprintf('Could not move "%s" back to "%s".', $backup, $from));
        }
    }

    /**
     * @param array<string, string> $hashes
     */
    private function undoMove(StatePaths $paths, string $tx, string $to, array $hashes): void
    {
        $target = $paths->absolute($to);
        if (!file_exists($target) && !is_link($target)) {
            return;
        }
        try {
            $current = is_dir($target) && !is_link($target) ? DirectoryHashes::of($target) : null;
        } catch (InstallException) {
            $current = null;
        }
        if ($current !== $hashes) {
            throw self::failed(sprintf(
                '"%s" differs from what the interrupted operation installed, so it was not deleted.',
                $to,
            ));
        }
        // One rename takes the directory out of the project; a kill while deleting it then leaves only staging.
        $trash = $paths->absolute($paths->staging($tx)) . '/undone-' . bin2hex(random_bytes(4));
        StatePaths::makeDirectory(dirname($trash));
        if (!@rename($target, $trash)) {
            throw self::failed(sprintf('Could not move "%s" out of the project.', $to));
        }
        self::removeQuietly($trash);
    }

    /**
     * Deletes a directory of `.yiipress/`; what cannot be deleted now is removed by a later recovery.
     */
    private static function removeQuietly(string $directory): void
    {
        try {
            DirectoryRemover::remove($directory);
        } catch (RuntimeException) {
        }
    }

    private static function failed(string $problem): InstallException
    {
        return new InstallException(
            'recovery_failed',
            $problem . ' The journal and backups in .yiipress/ were kept.',
            solution: 'Check themes/, yiipress.lock and config.yaml by hand, then delete .yiipress/journal.json.',
        );
    }
}
