<?php

declare(strict_types=1);

namespace YiiPress\Web\LiveReload;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_any;
use function constant;
use function inotify_add_watch;
use function is_dir;

/**
 * Inotify watches for every directory under the live reload roots, including directories created after start.
 */
final readonly class InotifyWatches
{
    /**
     * @param resource $stream an `inotify_init()` stream
     * @param list<string> $roots directories to watch recursively; missing ones are skipped
     */
    public function __construct(
        private mixed $stream,
        private array $roots,
    ) {}

    /**
     * Safe to repeat: adding a watch to a watched directory keeps its descriptor.
     */
    public function addAll(): void
    {
        foreach ($this->directories() as $directory) {
            @inotify_add_watch($this->stream, $directory, self::mask());
        }
    }

    /**
     * Adds watches for directories created or moved in, so changes inside them are seen too.
     *
     * @param array<array-key, array{mask: int, ...}> $events as returned by `inotify_read()`
     */
    public function update(array $events): void
    {
        if (array_any($events, static fn(array $event): bool => ($event['mask'] & constant('IN_ISDIR')) !== 0)) {
            $this->addAll();
        }
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        $directories = [];

        foreach ($this->roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $directories[] = $root;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
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

    private static function mask(): int
    {
        return constant('IN_ATTRIB')
            | constant('IN_CLOSE_WRITE')
            | constant('IN_CREATE')
            | constant('IN_DELETE')
            | constant('IN_DELETE_SELF')
            | constant('IN_MODIFY')
            | constant('IN_MOVE_SELF')
            | constant('IN_MOVED_FROM')
            | constant('IN_MOVED_TO');
    }
}
