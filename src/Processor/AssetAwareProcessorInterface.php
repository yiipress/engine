<?php

declare(strict_types=1);

namespace App\Processor;

/**
 * Processors that need JS/CSS assets implement this interface
 * alongside {@see ContentProcessorInterface}.
 *
 * The pipeline collects assets from all aware processors
 * and passes them to the theme for rendering.
 */
interface AssetAwareProcessorInterface
{
    /**
     * HTML to inject into <head> when processed content needs it.
     * Returns empty string if no assets needed for this content.
     */
    public function headAssets(string $processedContent): string;

    /**
     * Static asset files to copy to output directory during build.
     *
     * @return array<string, string> source absolute path => target path relative to output dir
     */
    public function assetFiles(): array;
}
