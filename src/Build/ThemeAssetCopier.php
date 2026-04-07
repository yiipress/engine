<?php

declare(strict_types=1);

namespace App\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function strlen;

final class ThemeAssetCopier
{
    /**
     * @return int number of assets copied
     */
    public function copy(ThemeRegistry $themeRegistry, string $outputDir, ?AssetFingerprintManifest $assetManifest = null): int
    {
        $copied = 0;

        foreach ($this->mappings($themeRegistry) as $sourcePath => $targetRelativePath) {
            $resolvedTarget = $assetManifest?->resolve($targetRelativePath) ?? $targetRelativePath;
            $targetPath = $outputDir . '/' . $resolvedTarget;
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir) && !mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }

            copy($sourcePath, $targetPath);
            $copied++;
        }

        return $copied;
    }

    /**
     * @return array<string, string>
     */
    public function mappings(ThemeRegistry $themeRegistry): array
    {
        $assets = [];

        foreach ($themeRegistry->all() as $theme) {
            $assetsDir = $theme->path . '/assets';
            if (!is_dir($assetsDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($assetsDir, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if (!$item->isFile()) {
                    continue;
                }

                $relativePath = substr($item->getPathname(), strlen($assetsDir) + 1);
                $assets[$item->getPathname()] = 'assets/theme/' . $relativePath;
            }
        }

        return $assets;
    }
}
