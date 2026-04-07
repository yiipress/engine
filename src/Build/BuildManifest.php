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
    /** @var array<string, array{hash: string, outputs: list<string>}> */
    private array $entries = [];

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

        $this->entries = $data;
    }

    public function save(): void
    {
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents(
            $this->manifestPath,
            json_encode($this->entries, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
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

        return $this->entries[$sourceFile]['hash'] !== hash_file('xxh128', $sourceFile);
    }

    /**
     * @param list<string> $outputs
     */
    public function record(string $sourceFile, array $outputs): void
    {
        $this->entries[$sourceFile] = [
            'hash' => hash_file('xxh128', $sourceFile),
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
     * @return array<string, array{hash: string, outputs: list<string>}>
     */
    public function entries(): array
    {
        return $this->entries;
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
