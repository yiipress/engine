<?php

declare(strict_types=1);

namespace App\Build;

use DirectoryIterator;

use function hash;
use function hash_file;

final class BuildCache
{
    private string $cacheDir;
    private string $templateHash;

    /**
     * @param list<string> $templateDirs
     */
    public function __construct(string $cacheDir, array $templateDirs)
    {
        $this->cacheDir = $cacheDir;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o755, true);
        }

        $this->templateHash = $this->hashTemplateDirs($templateDirs);
    }

    public function get(string $sourceFilePath): ?string
    {
        $key = $this->buildKey($sourceFilePath);
        $cachePath = $this->cacheDir . '/' . $key;

        if (!is_file($cachePath)) {
            return null;
        }

        return file_get_contents($cachePath);
    }

    public function set(string $sourceFilePath, string $html): void
    {
        $key = $this->buildKey($sourceFilePath);
        file_put_contents($this->cacheDir . '/' . $key, $html);
    }

    public function clear(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $iterator = new DirectoryIterator($this->cacheDir);
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            unlink($item->getPathname());
        }
    }

    private function buildKey(string $sourceFilePath): string
    {
        $fileHash = hash_file('xxh128', $sourceFilePath);
        return hash('xxh128', $fileHash . $this->templateHash);
    }

    /**
     * @param list<string> $templateDirs
     */
    private function hashTemplateDirs(array $templateDirs): string
    {
        $hashes = '';
        foreach ($templateDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.php');
            sort($files);
            foreach ($files as $file) {
                $hashes .= hash_file('xxh128', $file);
            }
        }

        return hash('xxh128', $hashes);
    }
}
