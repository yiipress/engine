<?php

declare(strict_types=1);

namespace YiiPress\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Yiisoft\Files\FileHelper;

use function dirname;
use function file_exists;
use function fclose;
use function fopen;
use function is_dir;
use function is_file;
use function sprintf;
use function strlen;
use function stream_copy_to_stream;
use function substr;

final readonly class ThemeInitializer
{
    public function initialize(Theme $theme, string $targetDir): int
    {
        if (!is_dir($theme->path)) {
            throw new RuntimeException(sprintf('Theme source directory "%s" was not found.', $theme->path));
        }

        if (is_file($targetDir)) {
            throw new RuntimeException(sprintf('Target path "%s" is a file.', $targetDir));
        }

        $files = $this->files($theme, $targetDir);

        foreach ($files as $sourcePath => $targetPath) {
            if (file_exists($targetPath)) {
                throw new RuntimeException(sprintf('Target file already exists: "%s".', $targetPath));
            }
        }

        foreach ($files as $sourcePath => $targetPath) {
            FileHelper::ensureDirectory(dirname($targetPath), 0o755);

            $this->copyWithoutOverwrite($sourcePath, $targetPath);
        }

        return count($files);
    }

    private function copyWithoutOverwrite(string $sourcePath, string $targetPath): void
    {
        $source = @fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $sourcePath, $targetPath));
        }

        try {
            $target = @fopen($targetPath, 'xb');
            if ($target === false) {
                if (file_exists($targetPath)) {
                    throw new RuntimeException(sprintf('Target file already exists: "%s".', $targetPath));
                }

                throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $sourcePath, $targetPath));
            }

            try {
                if (stream_copy_to_stream($source, $target) === false) {
                    throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $sourcePath, $targetPath));
                }
            } finally {
                fclose($target);
            }
        } finally {
            fclose($source);
        }
    }

    /**
     * @return array<string, string> source path => target path
     */
    private function files(Theme $theme, string $targetDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme->path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $relativePath = substr($sourcePath, strlen($theme->path) + 1);
            $files[$sourcePath] = $targetDir . '/' . $relativePath;
        }

        return $files;
    }
}
