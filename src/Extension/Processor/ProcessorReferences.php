<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

use Throwable;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Content\Parser\SiteConfigParser;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Processor\ProjectProcessorLoader;

use function array_pop;
use function basename;
use function explode;
use function implode;
use function is_file;
use function realpath;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Finds the processor paths of `config.yaml` that point into a package directory, so the package is not removed
 * while the build loads it. Paths resolve as the build resolves them, through symbolic links too; discovered
 * `processors/*.php` count, as run before Markdown, when they are links into the package. Files that `require`
 * package files are not found.
 */
final readonly class ProcessorReferences
{
    public function __construct(private SiteConfigParser $configParser = new SiteConfigParser()) {}

    /**
     * @param string $contentDir Absolute content directory, the one holding `config.yaml`.
     * @param string $packageDir Absolute package directory.
     * @return list<ProcessorReference>
     *
     * @throws InstallException `content_unreadable` when `config.yaml` cannot be parsed.
     */
    public function find(string $contentDir, string $packageDir): array
    {
        $configFile = $contentDir . '/config.yaml';
        if (!is_file($configFile)) {
            return [];
        }
        $config = $this->config($configFile, $packageDir);

        $references = [];
        $lists = [
            ['content', 'before_markdown', $config->contentBeforeMarkdown],
            ['content', 'after_markdown', $config->contentAfterMarkdown],
            ['feed', 'before_markdown', $config->feedBeforeMarkdown],
            ['feed', 'after_markdown', $config->feedAfterMarkdown],
        ];
        foreach ($lists as [$pipeline, $stage, $paths]) {
            foreach ($paths as $path) {
                $absolute = str_starts_with($path, '/') ? $path : $contentDir . '/' . $path;
                $packagePath = self::packagePath($absolute, $packageDir);
                if ($packagePath !== null) {
                    $references[] = new ProcessorReference($pipeline, $stage, $path, $packagePath);
                }
            }
        }
        if ($config->discover) {
            foreach (new ProjectProcessorLoader($contentDir, $configFile)->discoveredPaths() as $path) {
                $packagePath = self::packagePath($path, $packageDir);
                if ($packagePath !== null) {
                    $references[] = new ProcessorReference(
                        'content',
                        'before_markdown',
                        'processors/' . basename($path),
                        $packagePath,
                        discovered: true,
                    );
                }
            }
        }

        return $references;
    }

    /**
     * @throws InstallException `content_unreadable` when `config.yaml` cannot be parsed.
     */
    private function config(string $configFile, string $packageDir): ProcessorConfig
    {
        try {
            return $this->configParser->parse($configFile)->processors;
        } catch (Throwable $exception) {
            throw new InstallException(
                'content_unreadable',
                sprintf(
                    'The configuration in "%s" could not be read, so it is unknown whether it enables "%s": %s',
                    $configFile,
                    $packageDir,
                    $exception->getMessage(),
                ),
                solution: 'Fix config.yaml so that the build reads it, then run the command again.',
                previous: $exception,
            );
        }
    }

    /**
     * Where the path, as written or with its links resolved, lies in the package directory.
     *
     * @return string|null Relative to the package directory; `null` when it is outside.
     */
    private static function packagePath(string $path, string $packageDir): ?string
    {
        $package = self::normalise($packageDir) . '/';
        $lexical = self::normalise($path);
        if (str_starts_with($lexical, $package)) {
            return substr($lexical, strlen($package));
        }
        $realPackage = realpath($packageDir);
        $realPath = realpath($path);
        if ($realPath !== false && $realPackage !== false && str_starts_with($realPath, $realPackage . '/')) {
            return substr($realPath, strlen($realPackage) + 1);
        }

        return null;
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

        return '/' . implode('/', $segments);
    }
}
