<?php

declare(strict_types=1);

namespace YiiPress\Extension\Transaction;

use JsonException;
use YiiPress\Extension\Install\InstallException;

use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `.yiipress/journal.json`: the steps of a transaction, written before the first change to the project.
 *
 * Steps are `move_dir` (`from`, `to`, `hashes` of the moved files) and `replace_file` (`path`, `staged` new
 * contents, `backup` of the old file or `null` when there was none). Paths are relative to the project root.
 */
final readonly class Journal
{
    public const string PREPARED = 'prepared';
    public const string COMMITTED = 'committed';

    /**
     * @param list<array{kind: 'move_dir', from: string, to: string, hashes: array<string, string>}|array{kind: 'replace_file', path: string, staged: string, backup: string|null}> $steps
     */
    public function __construct(
        public string $tx,
        public string $operation,
        public string $state,
        public array $steps,
    ) {}

    public function withState(string $state): self
    {
        return new self($this->tx, $this->operation, $state, $this->steps);
    }

    public function write(StatePaths $paths): void
    {
        AtomicFile::write($paths->journal(), json_encode([
            'journal' => 1,
            'tx' => $this->tx,
            'operation' => $this->operation,
            'state' => $this->state,
            'steps' => $this->steps,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    /**
     * @throws InstallException `recovery_failed` when the journal cannot be read or is malformed.
     */
    public static function read(StatePaths $paths): self
    {
        $json = @file_get_contents($paths->journal());
        try {
            $data = $json === false ? null : json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }
        if (
            !is_array($data)
            || ($data['journal'] ?? null) !== 1
            || !is_string($data['tx'] ?? null)
            || preg_match('/^[0-9a-f]{16}$/D', $data['tx']) !== 1
            || !is_string($data['operation'] ?? null)
            || !in_array($data['state'] ?? null, [self::PREPARED, self::COMMITTED], true)
            || !is_array($data['steps'] ?? null)
        ) {
            throw self::malformed($paths);
        }

        $steps = [];
        foreach ($data['steps'] as $step) {
            $steps[] = self::step($step, $data['tx'], $paths);
        }

        return new self($data['tx'], $data['operation'], $data['state'], $steps);
    }

    /**
     * @return array{kind: 'move_dir', from: string, to: string, hashes: array<string, string>}|array{kind: 'replace_file', path: string, staged: string, backup: string|null}
     */
    private static function step(mixed $step, string $tx, StatePaths $paths): array
    {
        if (!is_array($step)) {
            throw self::malformed($paths);
        }
        $isTransactionPath = static fn(string $path): bool => JournalPaths::isTransactionPath($path, $tx, $paths);
        if (($step['kind'] ?? null) === 'move_dir' && is_array($step['hashes'] ?? null)) {
            $hashes = [];
            foreach ($step['hashes'] as $file => $hash) {
                if (!is_string($hash)) {
                    throw self::malformed($paths);
                }
                $hashes[self::path((string) $file, $paths, JournalPaths::isPortable(...))] = $hash;
            }

            return [
                'kind' => 'move_dir',
                'from' => self::path($step['from'] ?? null, $paths, $isTransactionPath),
                'to' => self::path($step['to'] ?? null, $paths, JournalPaths::isDirectoryTarget(...)),
                'hashes' => $hashes,
            ];
        }
        if (($step['kind'] ?? null) === 'replace_file') {
            $backup = $step['backup'] ?? null;

            return [
                'kind' => 'replace_file',
                'path' => self::path($step['path'] ?? null, $paths, JournalPaths::isFileTarget(...)),
                'staged' => self::path($step['staged'] ?? null, $paths, $isTransactionPath),
                'backup' => $backup === null ? null : self::path($backup, $paths, $isTransactionPath),
            ];
        }

        throw self::malformed($paths);
    }

    /**
     * @param callable(string): bool $allowed
     */
    private static function path(mixed $path, StatePaths $paths, callable $allowed): string
    {
        if (!is_string($path) || !$allowed($path)) {
            throw self::malformed($paths);
        }

        return $path;
    }

    private static function malformed(StatePaths $paths): InstallException
    {
        return new InstallException('recovery_failed', sprintf(
            '%s is unreadable or malformed, so the interrupted extension operation cannot be undone automatically.',
            $paths->journal(),
        ), solution: 'Check themes/, yiipress.lock and config.yaml by hand, then delete the .yiipress directory.');
    }
}
