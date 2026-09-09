<?php

declare(strict_types=1);

namespace YiiPress\Build;

use JsonException;
use DateTimeImmutable;
use RuntimeException;
use WeakMap;
use YiiPress\Content\Model\Entry;

/** Dependency fingerprints for shared outputs, committed only after a successful build. */
final class SharedOutputCache
{
    private const int VERSION = 1;
    /** @var array<string, string> */
    private array $previous = [];
    /** @var array<string, string> */
    private array $current = [];
    /** @var WeakMap<Entry, string> */
    private WeakMap $entryKeys;
    /** @var array<string, string> */
    private array $sourceHashes = [];
    /** @var array<string, list<string>> */
    private array $previousGroups = [];
    /** @var array<string, list<string>> */
    private array $currentGroups = [];
    private bool $valid = false;
    private string $context = '';
    private bool $reuse = false;
    private string $scope = '';
    private string $references = '';
    private ?int $nextTransition = null;

    public function __construct(private readonly string $path, private string $outputDir)
    {
        $this->entryKeys = new WeakMap();
        if (!is_file($path)) {
            return;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }
        if (!is_array($data) || ($data['version'] ?? null) !== self::VERSION || !is_array($data['outputs'] ?? null)) {
            return;
        }
        foreach ($data['outputs'] as $relative => $hash) {
            if (!is_string($relative) || !self::validPath($relative) || !is_string($hash)) {
                return;
            }
        }
        if (!is_string($data['scope'] ?? null) || (isset($data['nextTransition']) && !is_int($data['nextTransition']))) {
            return;
        }
        $groups = $data['groups'] ?? [];
        if (!is_array($groups)) {
            return;
        }
        foreach ($groups as $group => $paths) {
            if (!is_string($group) || !is_array($paths) || !array_is_list($paths)) {
                return;
            }
            foreach ($paths as $relative) {
                if (!is_string($relative) || !isset($data['outputs'][$relative])) {
                    return;
                }
            }
        }
        /** @var array<string, list<string>> $groups */
        $this->previousGroups = $groups;
        if (!is_string($data['references'] ?? '')) {
            return;
        }
        $this->references = $data['references'] ?? '';
        $this->scope = $data['scope'];
        $this->nextTransition = $data['nextTransition'] ?? null;
        $this->previous = $data['outputs'];
        $this->valid = true;
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    public function matchesScope(mixed $scope): bool
    {
        return $this->scope === hash('xxh128', serialize($scope));
    }

    public function referencesChanged(string $signature): bool
    {
        $changed = $signature !== $this->references;
        $this->references = $signature;
        $this->context = hash('xxh128', $this->context . $signature);
        return $changed;
    }

    public function complete(): bool
    {
        if (!$this->valid || ($this->nextTransition !== null && $this->nextTransition <= time())) {
            return false;
        }
        return array_all($this->previous, fn($_, $relative) => is_file($this->outputDir . '/' . $relative));
    }

    public function begin(string $outputDir, mixed $scope, mixed $context, bool $reuse): void
    {
        $this->outputDir = $outputDir;
        $this->scope = hash('xxh128', serialize($scope));
        $this->nextTransition = null;
        $this->context = hash('xxh128', serialize([self::VERSION, $context]));
        $this->reuse = $reuse;
        // A failed build may have overwritten some outputs. Never reuse its old fingerprints.
        if (is_file($this->path) && !unlink($this->path)) {
            throw new RuntimeException('Unable to invalidate shared output cache: ' . $this->path);
        }
    }

    /** @param list<string> $outputs Paths relative to the output directory. */
    public function needsWrite(array $outputs, mixed $dependencies): bool
    {
        $hash = hash('xxh128', $this->context . serialize($dependencies));
        $write = !$this->reuse;
        foreach ($outputs as $relative) {
            if (!self::validPath($relative)) {
                throw new RuntimeException('Invalid shared output path: ' . $relative);
            }
            $this->current[$relative] = $hash;
            if (($this->previous[$relative] ?? null) !== $hash || !is_file($this->outputDir . '/' . $relative)) {
                $write = true;
            }
        }
        return $write;
    }

    public function groupNeedsWrite(string $group, mixed $dependencies): bool
    {
        $paths = $this->previousGroups[$group] ?? [];
        if ($paths === []) {
            return true;
        }
        if ($this->needsWrite($paths, $dependencies)) {
            foreach ($paths as $path) {
                unset($this->current[$path]);
            }
            return true;
        }
        $this->currentGroups[$group] = $paths;
        return false;
    }

    /** @param list<string> $paths */
    public function recordGroup(string $group, array $paths, mixed $dependencies): void
    {
        $this->needsWrite($paths, $dependencies);
        $this->currentGroups[$group] = $paths;
    }

    /** @param array<string, array{hash: string, outputs: list<string>, mtime?: int, size?: int}> $sources */
    public function useRecordedSources(array $sources): void
    {
        foreach ($sources as $path => $source) {
            $this->sourceHashes[$path] = $source['hash'];
        }
    }

    /** @param list<Entry> $entries
     * @return list<string>
     */
    public function entryKeys(array $entries): array
    {
        $keys = [];
        foreach ($entries as $entry) {
            if (!isset($this->entryKeys[$entry])) {
                $sourceHash = $this->sourceHashes[$entry->filePath] ?? hash_file('xxh128', $entry->filePath);
                if ($sourceHash === false) {
                    throw new RuntimeException('Unable to hash entry: ' . $entry->filePath);
                }
                // Public metadata plus source content; lazy body/summary caches are not dependencies.
                $this->entryKeys[$entry] = hash('xxh128', serialize([get_object_vars($entry), $sourceHash]));
            }
            $keys[] = $this->entryKeys[$entry];
        }
        return $keys;
    }

    /** @param list<string> $protectedOutputs Absolute paths now owned by entries or assets. */
    public function removeObsolete(array $protectedOutputs): void
    {
        $protected = array_fill_keys($protectedOutputs, true);
        foreach ($this->previous as $relative => $_) {
            $path = $this->outputDir . '/' . $relative;
            if (!isset($this->current[$relative]) && !isset($protected[$path]) && is_file($path)) {
                if (!unlink($path)) {
                    throw new RuntimeException('Unable to remove obsolete output: ' . $path);
                }
            }
        }
    }

    /** @param list<Entry> $entries */
    public function trackFutureEntries(array $entries, DateTimeImmutable $buildTime): void
    {
        foreach ($entries as $entry) {
            $date = $entry->date?->getTimestamp();
            if ($date !== null && $entry->date > $buildTime && ($this->nextTransition === null || $date < $this->nextTransition)) {
                $this->nextTransition = $date;
            }
        }
    }

    public function save(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create cache directory: ' . $dir);
        }
        FileWriter::writeAtomic($this->path, json_encode([
            'version' => self::VERSION,
            'scope' => $this->scope,
            'references' => $this->references,
            'nextTransition' => $this->nextTransition,
            'outputs' => $this->current,
            'groups' => $this->currentGroups,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function validPath(string $path): bool
    {
        return $path !== '' && !str_starts_with($path, '/') && !str_contains($path, '\\')
            && !str_contains($path, ':') && !str_contains($path, "\0")
            && !in_array('..', explode('/', $path), true) && !in_array('.', explode('/', $path), true);
    }
}
