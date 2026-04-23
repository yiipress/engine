<?php

declare(strict_types=1);

namespace App\Build;

use function hash_file;
use function hash;
use function ltrim;
use function pathinfo;
use function substr;

final class AssetFingerprintManifest
{
    /** @var array<string, string> */
    private array $entries = [];

    private ?string $signature = null;

    public function register(string $logicalPath, string $sourceFilePath): string
    {
        $logicalPath = self::normalizePath($logicalPath);
        $fingerprintedPath = self::fingerprintPath($logicalPath, $sourceFilePath);
        $this->entries[$logicalPath] = $fingerprintedPath;
        $this->signature = null;

        return $fingerprintedPath;
    }

    public function resolve(string $path): string
    {
        $path = self::normalizePath($path);

        return $this->entries[$path] ?? $path;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function signature(): string
    {
        if ($this->signature !== null) {
            return $this->signature;
        }

        $entries = $this->entries;
        ksort($entries);

        return $this->signature = hash('xxh128', json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function normalizePath(string $path): string
    {
        return ltrim($path, '/');
    }

    public static function fingerprintPath(string $logicalPath, string $sourceFilePath): string
    {
        $logicalPath = self::normalizePath($logicalPath);
        $extension = pathinfo($logicalPath, PATHINFO_EXTENSION);
        $directory = pathinfo($logicalPath, PATHINFO_DIRNAME);
        $filename = pathinfo($logicalPath, PATHINFO_FILENAME);
        $hash = substr(hash_file('xxh128', $sourceFilePath), 0, 12);

        $fingerprintedName = $filename . '.' . $hash;
        if ($extension !== '') {
            $fingerprintedName .= '.' . $extension;
        }

        if ($directory === '.' || $directory === '') {
            return $fingerprintedName;
        }

        return $directory . '/' . $fingerprintedName;
    }
}
