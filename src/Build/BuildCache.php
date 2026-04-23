<?php

declare(strict_types=1);

namespace App\Build;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use RuntimeException;

use function hash;
use function hash_file;

final class BuildCache
{
    private string $templateHash;

    /**
     * @param list<string> $templateDirs
     */
    public function __construct(
        private readonly string $cacheDir,
        array $templateDirs
    ) {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0o755, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->cacheDir));
        }

        $this->templateHash = $this->hashTemplateDirs($templateDirs);
    }

    public function get(string $sourceFilePath, string $context = ''): ?string
    {
        $key = $this->buildKey($sourceFilePath, $context);
        $cachePath = $this->cacheDir . '/' . $key;

        if (!is_file($cachePath)) {
            return null;
        }

        $contents = file_get_contents($cachePath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read cache file "%s".', $cachePath));
        }

        return $contents;
    }

    public function set(string $sourceFilePath, string $html, string $context = ''): void
    {
        $key = $this->buildKey($sourceFilePath, $context);
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

    private function buildKey(string $sourceFilePath, string $context = ''): string
    {
        $fileHash = $this->hashFile($sourceFilePath);
        return hash('xxh128', $fileHash . $this->templateHash . $context);
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
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $files[] = $file->getPathname();
            }

            sort($files);
            foreach ($files as $file) {
                $hashes .= $file . ':' . $this->hashFile($file);
            }
        }

        return hash('xxh128', $hashes);
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('xxh128', $path);
        if ($hash === false) {
            throw new RuntimeException(sprintf('Unable to hash file "%s".', $path));
        }

        return $hash;
    }
}
