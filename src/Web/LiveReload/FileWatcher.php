<?php

declare(strict_types=1);

namespace App\Web\LiveReload;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function fclose;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function md5;
use function stream_select;
use function stream_set_blocking;
use function sys_get_temp_dir;

final class FileWatcher
{
    private string $checksumFile;

    /**
     * @param list<string> $directories
     */
    public function __construct(private readonly array $directories)
    {
        if (!function_exists('inotify_init')) {
            throw new \RuntimeException('YiiPress live reload requires the PHP inotify extension.');
        }

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

    public function waitForChanges(int $timeoutMilliseconds = 20_000): bool
    {
        $this->ensureBaseline();

        if ($timeoutMilliseconds <= 0) {
            return $this->hasChanges();
        }

        $changed = $this->waitForNativeEvents($timeoutMilliseconds);
        if ($changed) {
            // Refresh the persisted checksum after a native change so future checks stay in sync.
            $this->saveChecksum($this->computeChecksum());
        }

        return $changed;
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

    private function ensureBaseline(): void
    {
        if ($this->loadChecksum() === null) {
            $this->saveChecksum($this->computeChecksum());
        }
    }

    private function waitForNativeEvents(int $timeoutMilliseconds): bool
    {
        $stream = inotify_init();
        if ($stream === false) {
            throw new \RuntimeException('Unable to initialize inotify for live reload.');
        }

        stream_set_blocking($stream, false);

        $watchDescriptors = [];
        foreach ($this->watchedDirectories() as $directory) {
            $watchDescriptor = @inotify_add_watch($stream, $directory, $this->nativeWatchMask());
            if ($watchDescriptor !== false) {
                $watchDescriptors[] = $watchDescriptor;
            }
        }

        if ($watchDescriptors === []) {
            fclose($stream);
            return false;
        }

        $read = [$stream];
        $write = null;
        $except = null;
        $seconds = intdiv($timeoutMilliseconds, 1000);
        $microseconds = ($timeoutMilliseconds % 1000) * 1000;
        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

        if ($ready === false || $ready === 0) {
            fclose($stream);
            return false;
        }

        $events = inotify_read($stream);
        fclose($stream);

        return is_array($events) && $events !== [];
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

    /**
     * @return list<string>
     */
    private function watchedDirectories(): array
    {
        $directories = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $directories[] = $directory;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if ($item->isDir()) {
                    $directories[] = $item->getPathname();
                }
            }
        }

        return $directories;
    }

    private function nativeWatchMask(): int
    {
        return (int) constant('IN_ATTRIB')
            | (int) constant('IN_CLOSE_WRITE')
            | (int) constant('IN_CREATE')
            | (int) constant('IN_DELETE')
            | (int) constant('IN_DELETE_SELF')
            | (int) constant('IN_MODIFY')
            | (int) constant('IN_MOVE_SELF')
            | (int) constant('IN_MOVED_FROM')
            | (int) constant('IN_MOVED_TO');
    }
}
