<?php

declare(strict_types=1);

namespace App\Build;

use RuntimeException;

use function dirname;
use function in_array;
use function hash_file;
use function is_array;

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
            $this->entries = [];
            return;
        }

        $json = file_get_contents($this->manifestPath);
        if ($json === false) {
            $this->entries = [];
            return;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            $this->entries = [];
            return;
        }

        if (isset($data['entries']) && is_array($data['entries'])) {
            $this->entries = $data['entries'];
            $this->configFiles = isset($data['configFiles']) && is_array($data['configFiles']) ? array_values($data['configFiles']) : [];
            $this->trackedDirectories = isset($data['trackedDirectories']) && is_array($data['trackedDirectories']) ? $data['trackedDirectories'] : [];
            return;
        }

        $this->entries = $data;
        $this->configFiles = [];
        $this->trackedDirectories = [];
    }

    public function save(): void
    {
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents(
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
        clearstatcache(true, $sourceFile);
        $this->entries[$sourceFile] = [
            'hash' => hash_file('xxh128', $sourceFile),
            'mtime' => (int) filemtime($sourceFile),
            'size' => (int) filesize($sourceFile),
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
        $this->configFiles = array_values($configFiles);
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
