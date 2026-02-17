<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class FileWatcher
{
    private int $lastChecksum = 0;

    /**
     * @param list<string> $directories
     */
    public function __construct(private array $directories) {}

    public function hasChanges(): bool
    {
        $checksum = $this->computeChecksum();

        if ($this->lastChecksum === 0) {
            $this->lastChecksum = $checksum;
            return false;
        }

        if ($checksum === $this->lastChecksum) {
            return false;
        }

        $this->lastChecksum = $checksum;
        return true;
    }

    private function computeChecksum(): int
    {
        $hash = 0;

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                $hash ^= crc32($file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize());
            }
        }

        return $hash;
    }
}
