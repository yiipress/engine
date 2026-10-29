<?php

declare(strict_types=1);

namespace YiiPress\Extension\Processor;

use Throwable;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Content\Parser\SiteConfigParser;
use YiiPress\Extension\Lock\LockedExtension;
use YiiPress\Extension\Manifest\ProcessorMetadata;
use YiiPress\Extension\Manifest\ProcessorStage;
use YiiPress\Processor\ProjectProcessorLoader;

use function array_map;
use function basename;
use function implode;
use function in_array;
use function is_file;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * The `processors` setting that enables an installed processor: the current setting with the entry points added
 * under their first declared stage, for pages and feeds. It is printed for the user to copy; YiiPress never writes
 * it. Since listing processors turns discovery off, discovered `processors/*.php` are listed first, in filename
 * order, as the build runs them today.
 */
final readonly class EnablementSnippet
{
    private const string PLAIN = '~^[A-Za-z0-9_][A-Za-z0-9_./-]*$~D';

    public function __construct(private SiteConfigParser $configParser = new SiteConfigParser()) {}

    /**
     * @param LockedExtension $locked Lock entry of the installed processor.
     * @return list<string> Lines to print.
     */
    public function forInstalled(string $projectRoot, LockedExtension $locked, ProcessorMetadata $processor): array
    {
        $contentDir = LockedProcessorDestination::contentDir($locked);
        $prefix = $contentDir === '.' ? '' : $contentDir . '/';

        return $this->lines(
            rtrim($projectRoot, '/') . '/' . $contentDir,
            $prefix . 'config.yaml',
            substr($locked->destination, strlen($prefix)),
            $processor,
        );
    }

    /**
     * @param string $contentDir Absolute content directory, the one holding `config.yaml`.
     * @param string $configLabel How to name `config.yaml` to the user, for example `content/config.yaml`.
     * @param string $packagePath Package directory relative to the content directory.
     * @return list<string> Lines to print.
     */
    public function lines(
        string $contentDir,
        string $configLabel,
        string $packagePath,
        ProcessorMetadata $processor,
    ): array {
        [$lists, $discovered, $unreadable] = $this->current($contentDir);
        $entryPoints = [];
        foreach ($processor->entryPoints as $entryPoint) {
            $path = $packagePath . '/' . $entryPoint->path;
            $stage = $entryPoint->stages[0]->value;
            foreach (['content', 'feed'] as $pipeline) {
                if (!in_array($path, $lists[$pipeline][$stage], true)) {
                    $lists[$pipeline][$stage][] = $path;
                }
            }
            $entryPoints[] = sprintf('%s (%s)', $path, implode(', ', array_map(
                static fn(ProcessorStage $stage): string => $stage->value,
                $entryPoint->stages,
            )));
        }

        $lines = [
            sprintf('Not enabled: installing changes no configuration. Entry points: %s.', implode('; ', $entryPoints)),
            sprintf('To run it, set "processors" in %s to:', $configLabel),
            '',
            ...self::yaml($lists),
            '',
            'List order is execution order. Remove the feed lists to leave feeds as they are.',
        ];
        if ($discovered !== []) {
            $lines[] = 'Listing processors turns off discovery of processors/*.php, so the discovered ones are listed '
                . 'first, in filename order, to keep them running.';
        }
        if ($unreadable !== null) {
            $lines[] = sprintf(
                'The current "processors" setting is not shown, since %s could not be read: %s',
                $configLabel,
                $unreadable,
            );
        }

        return $lines;
    }

    /**
     * The processors the build runs today, with discovered `processors/*.php` spelled out.
     *
     * @return array{array<string, array<string, list<string>>>, list<string>, ?string} Pipeline → stage → paths,
     * the discovered paths and why `config.yaml` could not be read, if it could not.
     */
    private function current(string $contentDir): array
    {
        $configFile = $contentDir . '/config.yaml';
        $unreadable = null;
        try {
            $config = is_file($configFile)
                ? $this->configParser->parse($configFile)->processors
                : new ProcessorConfig();
        } catch (Throwable $exception) {
            $unreadable = $exception->getMessage();
            $config = new ProcessorConfig(discover: false);
        }
        $discovered = $config->discover
            ? array_map(
                static fn(string $path): string => 'processors/' . basename($path),
                new ProjectProcessorLoader($contentDir, $configFile)->discoveredPaths(),
            )
            : [];
        $lists = [
            'content' => [
                'before_markdown' => [...$discovered, ...$config->contentBeforeMarkdown],
                'after_markdown' => $config->contentAfterMarkdown,
            ],
            'feed' => [
                'before_markdown' => [...$discovered, ...$config->feedBeforeMarkdown],
                'after_markdown' => $config->feedAfterMarkdown,
            ],
        ];

        return [$lists, $discovered, $unreadable];
    }

    /**
     * @param array<string, array<string, list<string>>> $lists Pipeline → stage → paths.
     * @return list<string>
     */
    private static function yaml(array $lists): array
    {
        $lines = ['processors:'];
        foreach ($lists as $pipeline => $stages) {
            $lines[] = '  ' . $pipeline . ':';
            foreach ($stages as $stage => $paths) {
                if ($paths === []) {
                    continue;
                }
                $lines[] = '    ' . $stage . ':';
                foreach ($paths as $path) {
                    $lines[] = '      - ' . (preg_match(self::PLAIN, $path) === 1
                        ? $path
                        : "'" . str_replace("'", "''", $path) . "'");
                }
            }
        }

        return $lines;
    }
}
