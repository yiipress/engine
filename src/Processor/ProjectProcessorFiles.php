<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Content\Model\ProcessorConfig;

use function array_unique;
use function array_values;
use function glob;
use function is_file;
use function preg_match;
use function preg_quote;
use function realpath;
use function sort;
use function str_starts_with;

use const GLOB_NOSORT;

/**
 * The files whose changes change what project processors do, which the build cache depends on: discovered and
 * configured processor files, and every file of an installed processor package one of them is in, since an entry
 * point may load the package's support files.
 */
final class ProjectProcessorFiles
{
    /** Installed processor packages, `<vendor>/<name>` below it, relative to the content directory. */
    public const string PACKAGES_DIR = 'extensions/processors';

    /**
     * @return list<string> Absolute paths, sorted.
     */
    public static function collect(string $contentDir, ProcessorConfig $config): array
    {
        $files = [];
        if ($config->discover) {
            $discovered = glob($contentDir . '/processors/*.php', GLOB_NOSORT);
            if ($discovered !== false) {
                $files = $discovered;
            }
        }

        $paths = [
            ...$config->contentBeforeMarkdown,
            ...$config->contentAfterMarkdown,
            ...$config->feedBeforeMarkdown,
            ...$config->feedAfterMarkdown,
        ];
        if ($paths !== []) {
            $realContentDir = realpath($contentDir);
            $packages = $realContentDir === false
                ? null
                : '~^' . preg_quote($realContentDir . '/' . self::PACKAGES_DIR . '/', '~') . '[^/]+/[^/]+/~';
            $tracked = [];
            foreach ($paths as $path) {
                $realPath = realpath(str_starts_with($path, '/') ? $path : $contentDir . '/' . $path);
                if ($realPath === false || !is_file($realPath)) {
                    continue;
                }
                $files[] = $realPath;
                if (
                    $packages !== null
                    && preg_match($packages, $realPath, $match) === 1
                    && !isset($tracked[$match[0]])
                ) {
                    $tracked[$match[0]] = true;
                    $files = [...$files, ...self::packageFiles($match[0])];
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function packageFiles(string $packageDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
