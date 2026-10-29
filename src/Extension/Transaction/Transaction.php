<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use Throwable;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Install\InstallException;

use function bin2hex;
use function copy;
use function count;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_link;
use function random_bytes;
use function rename;
use function sprintf;
use function strlen;

/**
 * Changes to project files that either all happen or are all undone, even if the process is killed.
 *
 * Steps are only recorded until {@see commit()}; it backs up the files to replace, writes the journal, then
 * applies the steps. A failure undoes the applied steps at once; a killed process leaves the journal for
 * {@see Recovery}. The caller holds the {@see MutationLock}.
 */
final class Transaction
{
    /** @var list<array{kind: 'move_dir', from: string, to: string, hashes: array<string, string>}|array{kind: 'replace_file', path: string, staged: string, backup: string|null}> */
    private array $steps = [];
    private bool $done = false;

    private function __construct(
        private readonly StatePaths $paths,
        public readonly string $tx,
        private readonly string $operation,
    ) {}

    public static function begin(string $projectRoot, string $operation = 'install'): self
    {
        $paths = new StatePaths($projectRoot);
        $paths->assertSafe();
        $transaction = new self($paths, bin2hex(random_bytes(8)), $operation);
        StatePaths::makeDirectory($transaction->stagingDirectory());

        return $transaction;
    }

    /**
     * Absolute path of the staging directory, on the same file system as the project.
     */
    public function stagingDirectory(): string
    {
        return $this->paths->absolute($this->paths->staging($this->tx));
    }

    /**
     * Relative path inside staging, for {@see moveDirectory()}.
     */
    public function stagingPath(string $path): string
    {
        return $this->paths->staging($this->tx) . '/' . $path;
    }

    /**
     * @param string $from Project-relative directory, normally in staging.
     * @param string $to Project-relative destination; must not exist.
     * @param array<string, string> $hashes Files of the directory → SHA-256; recovery deletes the moved
     *        directory only while it still matches them.
     */
    public function moveDirectory(string $from, string $to, array $hashes): void
    {
        self::checkTarget($to, JournalPaths::isDirectoryTarget($to));
        $this->steps[] = ['kind' => 'move_dir', 'from' => $from, 'to' => $to, 'hashes' => $hashes];
    }

    public function replaceFile(string $path, string $contents): void
    {
        self::checkTarget($path, JournalPaths::isFileTarget($path));
        $staged = $this->paths->staging($this->tx) . '/files/' . count($this->steps);
        $absolute = $this->paths->absolute($staged);
        StatePaths::makeDirectory(dirname($absolute));
        if (@file_put_contents($absolute, $contents) !== strlen($contents)) {
            throw new InstallException('filesystem_failed', sprintf('Could not write "%s".', $absolute));
        }
        $this->steps[] = ['kind' => 'replace_file', 'path' => $path, 'staged' => $staged, 'backup' => null];
    }

    public function commit(): void
    {
        foreach ($this->steps as $step) {
            $target = $step['kind'] === 'move_dir' ? $step['to'] : $step['path'];
            if (!JournalPaths::staysInside($target, $this->paths)) {
                throw new InstallException('filesystem_failed', sprintf(
                    '"%s" resolves to a location outside the project; nothing was changed.',
                    $target,
                ));
            }
        }
        $steps = $this->backUp();
        $journal = new Journal($this->tx, $this->operation, Journal::PREPARED, $steps);
        $journal->write($this->paths);

        $applied = [];
        try {
            foreach ($steps as $step) {
                $this->apply($step);
                $applied[] = $step;
            }
        } catch (Throwable $exception) {
            new Recovery()->undo($this->paths, new Journal($this->tx, $this->operation, Journal::PREPARED, $applied));
            $this->done = true;
            throw $exception;
        }

        $journal->withState(Journal::COMMITTED)->write($this->paths);
        Recovery::cleanUp($this->paths, $this->tx);
        $this->done = true;
    }

    private static function checkTarget(string $path, bool $allowed): void
    {
        if (!$allowed) {
            throw new InstallException('filesystem_failed', sprintf(
                'An extension installation cannot change "%s".',
                $path,
            ));
        }
    }

    /**
     * Deletes staging of a transaction that is not going to be committed.
     */
    public function discard(): void
    {
        if (!$this->done) {
            DirectoryRemover::remove($this->stagingDirectory());
            $this->done = true;
        }
    }

    /**
     * @return list<array{kind: 'move_dir', from: string, to: string, hashes: array<string, string>}|array{kind: 'replace_file', path: string, staged: string, backup: string|null}>
     */
    private function backUp(): array
    {
        $steps = [];
        foreach ($this->steps as $index => $step) {
            if ($step['kind'] === 'replace_file' && file_exists($this->paths->absolute($step['path']))) {
                $backup = $this->paths->backup($this->tx) . '/' . $index;
                StatePaths::makeDirectory(dirname($this->paths->absolute($backup)));
                if (!@copy($this->paths->absolute($step['path']), $this->paths->absolute($backup))) {
                    throw new InstallException('filesystem_failed', sprintf('Could not back up "%s".', $step['path']));
                }
                $step['backup'] = $backup;
            }
            $steps[] = $step;
        }

        return $steps;
    }

    /**
     * @param array{kind: 'move_dir', from: string, to: string, hashes: array<string, string>}|array{kind: 'replace_file', path: string, staged: string, backup: string|null} $step
     */
    private function apply(array $step): void
    {
        [$from, $to] = $step['kind'] === 'move_dir' ? [$step['from'], $step['to']] : [$step['staged'], $step['path']];
        $target = $this->paths->absolute($to);
        if ($step['kind'] === 'move_dir' && (file_exists($target) || is_link($target))) {
            throw new InstallException('destination_exists', sprintf('"%s" appeared during the installation.', $to));
        }
        StatePaths::makeDirectory(dirname($target));
        if (!@rename($this->paths->absolute($from), $target)) {
            throw new InstallException('filesystem_failed', sprintf(
                'Could not move "%s" to "%s"; the project directory must be on one file system.',
                $from,
                $to,
            ));
        }
    }
}
