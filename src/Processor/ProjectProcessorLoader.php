<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use Closure;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Content\Parser\InvalidContentConfigException;

use function glob;
use function implode;
use function is_callable;
use function is_file;
use function realpath;
use function sort;
use function str_starts_with;

use const GLOB_NOSORT;

final readonly class ProjectProcessorLoader
{
    public function __construct(
        private string $contentDir,
        private string $configPath,
    ) {}

    public function load(ProcessorConfig $config): ProjectProcessorSet
    {
        $discovered = $config->discover ? $this->discoveredPaths() : [];

        return new ProjectProcessorSet(
            contentBeforeMarkdown: $this->loadFiles([...$discovered, ...$config->contentBeforeMarkdown]),
            contentAfterMarkdown: $this->loadFiles($config->contentAfterMarkdown),
            feedBeforeMarkdown: $this->loadFiles([...$discovered, ...$config->feedBeforeMarkdown]),
            feedAfterMarkdown: $this->loadFiles($config->feedAfterMarkdown),
        );
    }

    /**
     * @return list<string>
     */
    public function discoveredPaths(): array
    {
        $files = glob($this->contentDir . '/processors/*.php', GLOB_NOSORT);
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $paths
     * @return list<ContentProcessorInterface>
     */
    private function loadFiles(array $paths): array
    {
        $processors = [];
        foreach ($paths as $path) {
            $processors[] = $this->loadFile($this->resolvePath($path));
        }

        return $processors;
    }

    private function loadFile(string $path): ContentProcessorInterface
    {
        /** @psalm-suppress UnresolvableInclude User-defined processor files are resolved at build time. */
        $processor = require $path;

        if ($processor instanceof ContentProcessorInterface) {
            return $processor;
        }

        if (is_callable($processor)) {
            return new CallbackContentProcessor(Closure::fromCallable($processor));
        }

        throw new InvalidContentConfigException(
            "Project processor file must return a content processor or callable: $path",
            $this->configPath,
            implode("\n", [
                'Return an implementation of YiiPress\Processor\ContentProcessorInterface:',
                'use YiiPress\Content\Model\Entry;',
                'use YiiPress\Processor\ContentProcessorInterface;',
                '',
                'return new class implements ContentProcessorInterface {',
                '    public function process(string $content, Entry $entry): string',
                '    {',
                '        return $content;',
                '    }',
                '};',
            ]),
        );
    }

    private function resolvePath(string $path): string
    {
        $candidate = str_starts_with($path, '/')
            ? $path
            : $this->contentDir . '/' . $path;

        $realPath = realpath($candidate);
        $realContentDir = realpath($this->contentDir);

        if ($realPath === false || $realContentDir === false || !is_file($realPath)) {
            throw new InvalidContentConfigException(
                "Project processor file does not exist: $path",
                $this->configPath,
                'Create the file under content/processors/ or remove it from the processors configuration.',
            );
        }

        if ($realPath !== $realContentDir && !str_starts_with($realPath, $realContentDir . '/')) {
            throw new InvalidContentConfigException(
                "Project processor file must stay inside the content directory: $path",
                $this->configPath,
                'Move the processor into content/processors/ and reference it with a relative path.',
            );
        }

        return $realPath;
    }
}
