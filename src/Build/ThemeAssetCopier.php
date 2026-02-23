<?php

declare(strict_types=1);

namespace App\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function strlen;

final class ThemeAssetCopier
{
    /**
     * @return int number of assets copied
     */
    public function copy(ThemeRegistry $themeRegistry, string $outputDir): int
    {
        $copied = 0;
        $targetBase = $outputDir . '/assets/theme';

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
                $targetPath = $targetBase . '/' . $relativePath;
                $targetDir = dirname($targetPath);

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0o755, true);
                }

                copy($item->getPathname(), $targetPath);
                $copied++;
            }
        }

        return $copied;
    }
}
