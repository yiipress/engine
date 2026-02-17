<?php

declare(strict_types=1);

namespace App\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ContentAssetCopier
{
    private const array EXCLUDED_EXTENSIONS = ['md', 'yaml', 'yml'];

    /**
     * @return int number of assets copied
     */
    public function copy(string $contentDir, string $outputDir): int
    {
        $copied = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if (in_array($extension, self::EXCLUDED_EXTENSIONS, true)) {
                continue;
            }

            $relativePath = substr($item->getPathname(), strlen($contentDir) + 1);

            if (str_starts_with($relativePath, 'authors/')) {
                continue;
            }

            $targetPath = $outputDir . '/' . $relativePath;
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0o755, true);
            }

            copy($item->getPathname(), $targetPath);
            $copied++;
        }

        return $copied;
    }

    /**
     * @return list<string> relative paths of assets that would be copied
     */
    public function collect(string $contentDir): array
    {
        $assets = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if (in_array($extension, self::EXCLUDED_EXTENSIONS, true)) {
                continue;
            }

            $relativePath = substr($item->getPathname(), strlen($contentDir) + 1);

            if (str_starts_with($relativePath, 'authors/')) {
                continue;
            }

            $assets[] = $relativePath;
        }

        sort($assets);
        return $assets;
    }
}
