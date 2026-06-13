<?php

declare(strict_types=1);

namespace YiiPress\Build;

use RuntimeException;

use function clearstatcache;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_file;
use function sprintf;
use function touch;

final class AssetFileWriter
{
    public function writeIfChanged(string $sourcePath, string $targetPath, bool $minify): bool
    {
        if (!$minify || !AssetMinifier::supports($sourcePath)) {
            return FileCopy::copyIfChanged($sourcePath, $targetPath);
        }

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new RuntimeException(sprintf('Unable to read asset "%s".', $sourcePath));
        }

        $content = AssetMinifier::minify($sourcePath, $content);
        if ($this->destinationMatchesContent($targetPath, $content)) {
            return false;
        }

        if (file_put_contents($targetPath, $content) === false) {
            throw new RuntimeException(sprintf('Unable to write asset "%s".', $targetPath));
        }

        $mtime = filemtime($sourcePath);
        if ($mtime !== false) {
            touch($targetPath, $mtime);
        }

        return true;
    }

    private function destinationMatchesContent(string $targetPath, string $content): bool
    {
        if (!is_file($targetPath)) {
            return false;
        }

        clearstatcache(true, $targetPath);
        $targetContent = file_get_contents($targetPath);

        return $targetContent === $content;
    }
}
