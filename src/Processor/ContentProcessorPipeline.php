<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;

final class ContentProcessorPipeline
{
    /** @var list<ContentProcessorInterface> */
    private array $processors;

    public function __construct(ContentProcessorInterface ...$processors)
    {
        $this->processors = array_values($processors);
    }

    public function process(string $content, Entry $entry): string
    {
        foreach ($this->processors as $processor) {
            $content = $processor->process($content, $entry);
        }

        return $content;
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
}
