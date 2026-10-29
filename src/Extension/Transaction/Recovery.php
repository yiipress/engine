<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\DirectoryHashes;
use YiiPress\Extension\Install\InstallException;

use function array_reverse;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_file;
use function is_link;
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
        if (!is_file($paths->journal())) {
            return null;
        }
        $journal = Journal::read($paths);
        if ($journal->state === Journal::COMMITTED) {
            self::cleanUp($paths, $journal->tx);

            return sprintf('The interrupted %s had completed; its temporary files were removed.', $journal->operation);
        }
        $this->undo($paths, $journal);

        return sprintf('The interrupted %s was undone.', $journal->operation);
    }

    /**
     * Undoes the steps of a prepared transaction in reverse order, then removes its files and journal.
     */
    public function undo(StatePaths $paths, Journal $journal): void
    {
        foreach ($journal->steps as $step) {
            $target = $step['kind'] === 'move_dir' ? $step['to'] : $step['path'];
            if (!JournalPaths::staysInside($target, $paths)) {
                throw self::failed(sprintf('"%s" resolves to a location outside the project.', $target));
            }
        }
        foreach (array_reverse($journal->steps) as $step) {
            if ($step['kind'] === 'move_dir') {
                $this->undoMove($paths, $step['to'], $step['hashes']);
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

    public static function cleanUp(StatePaths $paths, string $tx): void
    {
        DirectoryRemover::remove($paths->absolute($paths->staging($tx)));
        DirectoryRemover::remove($paths->absolute($paths->backup($tx)));
        if (is_file($paths->journal()) && !@unlink($paths->journal())) {
            throw new InstallException('filesystem_failed', sprintf('Could not delete "%s".', $paths->journal()));
        }
    }

    /**
     * @param array<string, string> $hashes
     */
    private function undoMove(StatePaths $paths, string $to, array $hashes): void
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
        DirectoryRemover::remove($target);
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
