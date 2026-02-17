<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use SplFileInfo;

final class FileWatcher
{
    private string $checksumFile;

    /**
     * @param list<string> $directories
     */
    public function __construct(private readonly array $directories)
    {
        $this->checksumFile = sys_get_temp_dir() . '/yiipress-filewatcher-' . md5(implode('|', $directories)) . '.checksum';
    }

    public function hasChanges(): bool
    {
        $checksum = $this->computeChecksum();
        $lastChecksum = $this->loadChecksum();

        if ($lastChecksum === null) {
            $this->saveChecksum($checksum);
            return false;
        }

        if ($checksum === $lastChecksum) {
            return false;
        }

        $this->saveChecksum($checksum);
        return true;
    }

    private function loadChecksum(): ?int
    {
        if (!is_file($this->checksumFile)) {
            return null;
        }

        $content = file_get_contents($this->checksumFile);
        if ($content === false) {
            return null;
        }

        return (int) $content;
    }

    private function saveChecksum(int $checksum): void
    {
        file_put_contents($this->checksumFile, (string) $checksum);
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
                /** @var SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                $hash ^= crc32($file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize());
            }
        }

        return $hash;
    }
}
