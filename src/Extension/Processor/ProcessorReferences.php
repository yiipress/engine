<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

use Throwable;
use YiiPress\Content\Parser\SiteConfigParser;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Processor\ProjectProcessorLoader;

use function array_pop;
use function basename;
use function explode;
use function implode;
use function is_file;
use function realpath;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

/**
 * Finds the processor paths of `config.yaml` that point into a package directory, so the package is not removed
 * while the build loads it. Paths resolve as the build resolves them, through symbolic links too; discovered
 * `processors/*.php` count when they are links into the package. Files that `require` package files are not found.
 */
final readonly class ProcessorReferences
{
    public function __construct(private SiteConfigParser $configParser = new SiteConfigParser()) {}

    /**
     * @param string $contentDir Absolute content directory, the one holding `config.yaml`.
     * @param string $packageDir Absolute package directory.
     * @return list<string> The references as `<pipeline>.<stage>: <path>` or `<path> (discovered)`.
     *
     * @throws InstallException `content_unreadable` when `config.yaml` cannot be parsed.
     */
    public function find(string $contentDir, string $packageDir): array
    {
        $configFile = $contentDir . '/config.yaml';
        if (!is_file($configFile)) {
            return [];
        }
        try {
            $config = $this->configParser->parse($configFile)->processors;
        } catch (Throwable $exception) {
            throw new InstallException(
                'content_unreadable',
                sprintf(
                    'The configuration in "%s" could not be read, so it is unknown whether it enables "%s": %s',
                    $configFile,
                    $packageDir,
                    $exception->getMessage(),
                ),
                solution: 'Fix config.yaml so that the build reads it, then run remove:processor again.',
                previous: $exception,
            );
        }

        $references = [];
        $lists = [
            'content.before_markdown' => $config->contentBeforeMarkdown,
            'content.after_markdown' => $config->contentAfterMarkdown,
            'feed.before_markdown' => $config->feedBeforeMarkdown,
            'feed.after_markdown' => $config->feedAfterMarkdown,
        ];
        foreach ($lists as $list => $paths) {
            foreach ($paths as $path) {
                $absolute = str_starts_with($path, '/') ? $path : $contentDir . '/' . $path;
                if (self::isInside($absolute, $packageDir)) {
                    $references[] = $list . ': ' . $path;
                }
            }
        }
        if ($config->discover) {
            foreach (new ProjectProcessorLoader($contentDir, $configFile)->discoveredPaths() as $path) {
                if (self::isInside($path, $packageDir)) {
                    $references[] = 'processors/' . basename($path) . ' (discovered)';
                }
            }
        }

        return $references;
    }

    /**
     * Whether the path, as written or with its links resolved, lies in the package directory.
     */
    private static function isInside(string $path, string $packageDir): bool
    {
        $package = rtrim($packageDir, '/') . '/';
        $realPackage = realpath($packageDir);
        $realPath = realpath($path);

        return str_starts_with(self::normalise($path), self::normalise($package))
            || ($realPath !== false && $realPackage !== false && str_starts_with($realPath, $realPackage . '/'));
    }

    /**
     * Drops `.` and empty segments and applies `..`, without touching the file system.
     */
    private static function normalise(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments) . (str_ends_with($path, '/') ? '/' : '');
    }
}
