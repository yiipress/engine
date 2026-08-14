<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function dirname;
use function in_array;
use function hash_file;
use function is_array;
use function is_file;
use function is_int;
use function is_string;

final class BuildManifest
{
    /** @var array<string, array{hash: string, outputs: list<string>, mtime?: int, size?: int}> */
    private array $entries = [];
    /** @var list<string> */
    private array $configFiles = [];
    /** @var array<string, int> */
    private array $trackedDirectories = [];

    public function __construct(
        private readonly string $manifestPath,
    ) {}

    public function load(): void
    {
        if (!is_file($this->manifestPath)) {
            $this->clear();
            return;
        }

        $json = file_get_contents($this->manifestPath);
        if ($json === false) {
            $this->clear();
            return;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->clear();
            return;
        }
        if (!is_array($data)) {
            $this->clear();
            return;
        }

        if (isset($data['entries']) && is_array($data['entries'])) {
            $entries = self::normalizeEntries($data['entries']);
            $configFiles = self::normalizeStringList($data['configFiles'] ?? []);
            $trackedDirectories = self::normalizeTrackedDirectories($data['trackedDirectories'] ?? []);
            if ($entries === null || $configFiles === null || $trackedDirectories === null) {
                $this->clear();
                return;
            }
            $this->entries = $entries;
            $this->configFiles = $configFiles;
            $this->trackedDirectories = $trackedDirectories;
            return;
        }

        $entries = self::normalizeEntries($data);
        if ($entries === null) {
            $this->clear();
            return;
        }
        $this->entries = $entries;
        $this->configFiles = [];
        $this->trackedDirectories = [];
    }

    private function clear(): void
    {
        $this->entries = [];
        $this->configFiles = [];
        $this->trackedDirectories = [];
    }

    /**
     * @return array<string, array{hash: string, outputs: list<string>, mtime?: int, size?: int}>|null
     */
    private static function normalizeEntries(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $entries = [];
        foreach ($value as $sourceFile => $entry) {
            if (!is_string($sourceFile) || !is_array($entry) || !is_string($entry['hash'] ?? null)) {
                return null;
            }
            $outputs = self::normalizeStringList($entry['outputs'] ?? null);
            if ($outputs === null) {
                return null;
            }
            $normalized = ['hash' => $entry['hash'], 'outputs' => $outputs];
            foreach (['mtime', 'size'] as $key) {
                if (isset($entry[$key])) {
                    if (!is_int($entry[$key])) {
                        return null;
                    }
                    $normalized[$key] = $entry[$key];
                }
            }
            /** @var array{hash: string, outputs: list<string>, mtime?: int, size?: int} $normalized */
            $entries[$sourceFile] = $normalized;
        }

        return $entries;
    }

    /** @return list<string>|null */
    private static function normalizeStringList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                return null;
            }
            $result[] = $item;
        }
        return $result;
    }

    /** @return array<string, int>|null */
    private static function normalizeTrackedDirectories(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $result = [];
        foreach ($value as $directory => $mtime) {
            if (!is_string($directory) || !is_int($mtime)) {
                return null;
            }
            $result[$directory] = $mtime;
        }
        return $result;
    }

    public function save(): void
    {
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        FileWriter::writeAtomic(
            $this->manifestPath,
            json_encode([
                'entries' => $this->entries,
                'configFiles' => $this->configFiles,
                'trackedDirectories' => $this->trackedDirectories,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    public function isChanged(string $sourceFile): bool
    {
        if (!isset($this->entries[$sourceFile])) {
            return true;
        }

        if (!is_file($sourceFile)) {
            return true;
        }

        clearstatcache(true, $sourceFile);
        $mtime = filemtime($sourceFile);
        $size = filesize($sourceFile);
        $storedMtime = $this->entries[$sourceFile]['mtime'] ?? null;
        $storedSize = $this->entries[$sourceFile]['size'] ?? null;

        if ($storedMtime !== null && $storedSize !== null && $storedMtime === $mtime && $storedSize === $size) {
            return false;
        }

        return $this->entries[$sourceFile]['hash'] !== hash_file('xxh128', $sourceFile);
    }

    /**
     * @param list<string> $outputs
     */
    public function record(string $sourceFile, array $outputs): void
    {
        if (!is_file($sourceFile)) {
            throw new RuntimeException("Unable to hash source file: $sourceFile");
        }
        clearstatcache(true, $sourceFile);
        $mtime = (int) filemtime($sourceFile);
        $size = (int) filesize($sourceFile);
        $stored = $this->entries[$sourceFile] ?? null;
        $hash = $stored !== null
            && ($stored['mtime'] ?? null) === $mtime
            && ($stored['size'] ?? null) === $size
            ? $stored['hash']
            : hash_file('xxh128', $sourceFile);
        if ($hash === false) {
            throw new RuntimeException("Unable to hash source file: $sourceFile");
        }

        $this->entries[$sourceFile] = [
            'hash' => $hash,
            'mtime' => $mtime,
            'size' => $size,
            'outputs' => $outputs,
        ];
    }

    /**
     * @param list<string> $outputs
     * @return list<string>
     */
    public function replace(string $sourceFile, array $outputs): array
    {
        $staleOutputs = [];

        if (isset($this->entries[$sourceFile])) {
            foreach ($this->entries[$sourceFile]['outputs'] as $outputFile) {
                if (!in_array($outputFile, $outputs, true)) {
                    $staleOutputs[] = $outputFile;
                }
            }
        }

        $this->record($sourceFile, $outputs);

        return $staleOutputs;
    }

    /**
     * @return array<string, array{hash: string, outputs: list<string>, mtime?: int, size?: int}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function sourceFiles(): array
    {
        return array_keys($this->entries);
    }

    /**
     * @param list<string> $configFiles
     */
    public function setConfigFiles(array $configFiles): void
    {
        $this->configFiles = $configFiles;
    }

    /**
     * @return list<string>
     */
    public function configFiles(): array
    {
        return $this->configFiles;
    }

    /**
     * @param array<string, int> $trackedDirectories
     */
    public function setTrackedDirectories(array $trackedDirectories): void
    {
        $this->trackedDirectories = $trackedDirectories;
    }

    public function hasTrackedDirectories(): bool
    {
        return $this->trackedDirectories !== [];
    }

    public function trackedDirectoriesChanged(): bool
    {
        foreach ($this->trackedDirectories as $directory => $storedMtime) {
            clearstatcache(true, $directory);
            if (!is_dir($directory)) {
                return true;
            }

            $mtime = filemtime($directory);
            if ($mtime === false || (int) $mtime !== $storedMtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $currentSourceFiles
     * @return list<string> output files that should be removed
     */
    public function removedOutputs(array $currentSourceFiles): array
    {
        $currentSet = array_flip($currentSourceFiles);
        $removed = [];

        foreach ($this->entries as $sourceFile => $data) {
            if (!isset($currentSet[$sourceFile])) {
                foreach ($data['outputs'] as $outputFile) {
                    $removed[] = $outputFile;
                }
                unset($this->entries[$sourceFile]);
            }
        }

        return $removed;
    }

    /**
     * @param list<string> $currentSourceFiles
     * @return list<string> source files that changed or are new
     */
    public function changedFiles(array $currentSourceFiles): array
    {
        $changed = [];
        foreach ($currentSourceFiles as $sourceFile) {
            if ($this->isChanged($sourceFile)) {
                $changed[] = $sourceFile;
            }
        }
        return $changed;
    }

    /**
     * @param list<string> $currentSourceFiles
     * @return list<string> source files whose recorded outputs are missing
     */
    public function missingOutputFiles(array $currentSourceFiles): array
    {
        $missing = [];

        foreach ($currentSourceFiles as $sourceFile) {
            if (!isset($this->entries[$sourceFile])) {
                continue;
            }

            foreach ($this->entries[$sourceFile]['outputs'] as $outputFile) {
                if (!is_file($outputFile)) {
                    $missing[] = $sourceFile;
                    break;
                }
            }
        }

        return $missing;
    }
}
