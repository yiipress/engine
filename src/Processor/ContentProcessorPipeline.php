<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\Toc\TocAwareInterface;

final class ContentProcessorPipeline
{
    /** @var list<ContentProcessorInterface> */
    private array $processors;

    /** @var list<ContentProcessorInterface> */
    private array $initialProcessors;

    public function __construct(ContentProcessorInterface ...$processors)
    {
        $this->initialProcessors = array_values($processors);
        $this->processors = $this->initialProcessors;
    }

    public function reset(): void
    {
        $this->processors = $this->initialProcessors;
    }

    /**
     * @param class-string<ContentProcessorInterface> $processorClass
     */
    public function insertBefore(string $processorClass, ContentProcessorInterface ...$processors): void
    {
        if ($processors === []) {
            return;
        }

        $position = $this->positionOf($processorClass);
        if ($position === null) {
            $this->processors = array_values([...$processors, ...$this->processors]);
            return;
        }

        $updated = $this->processors;
        array_splice($updated, $position, 0, $processors);
        $this->processors = array_values($updated);
    }

    /**
     * @param class-string<ContentProcessorInterface> $processorClass
     */
    public function insertAfter(string $processorClass, ContentProcessorInterface ...$processors): void
    {
        if ($processors === []) {
            return;
        }

        $position = $this->positionOf($processorClass);
        if ($position === null) {
            $this->processors = array_values([...$this->processors, ...$processors]);
            return;
        }

        $updated = $this->processors;
        array_splice($updated, $position + 1, 0, $processors);
        $this->processors = array_values($updated);
    }

    public function process(string $content, Entry $entry, ?string $rootPath = null): string
    {
        foreach ($this->processors as $processor) {
            if ($rootPath !== null && $processor instanceof RootPathAwareProcessorInterface) {
                $processor->applyRootPath($rootPath);
            }
            $content = $processor->process($content, $entry);
        }

        return $content;
    }

    public function applySiteConfig(SiteConfig $siteConfig): void
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof SiteConfigAwareProcessorInterface) {
                $processor->applySiteConfig($siteConfig);
            }
        }
    }

    public function collectHeadAssets(string $processedContent): string
    {
        $assets = '';
        foreach ($this->processors as $processor) {
            if ($processor instanceof AssetProcessorInterface) {
                $assets .= $processor->headAssets($processedContent);
            }
        }
        return $assets;
    }

    /**
     * Returns the table of contents collected by the first TocAwareInterface processor.
     *
     * @return list<array{id: string, text: string, level: int}>
     */
    public function collectToc(): array
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof TocAwareInterface) {
                return $processor->getToc();
            }
        }
        return [];
    }

    /**
     * @return array<string, string> source absolute path => target path relative to output dir
     */
    public function collectAssetFiles(): array
    {
        $files = [];
        foreach ($this->processors as $processor) {
            if ($processor instanceof AssetProcessorInterface) {
                $files += $processor->assetFiles();
            }
        }
        return $files;
    }

    /**
     * @param class-string<ContentProcessorInterface> $processorClass
     */
    private function positionOf(string $processorClass): ?int
    {
        foreach ($this->processors as $index => $processor) {
            if ($processor instanceof $processorClass) {
                return $index;
            }
        }

        return null;
    }
}
