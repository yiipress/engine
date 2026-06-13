<?php

declare(strict_types=1);

namespace YiiPress\Processor\LatexMath;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\AssetProcessorInterface;
use YiiPress\Processor\ContentProcessorInterface;

use function str_contains;

final readonly class LatexMathProcessor implements ContentProcessorInterface, AssetProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        return $content;
    }

    public function headAssets(string $processedContent): string
    {
        if (!str_contains($processedContent, '<x-equation')) {
            return '';
        }

        return <<<'HTML'
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script defer src="assets/plugins/latex-math.js"></script>
HTML;
    }

    public function assetFiles(): array
    {
        return [
            __DIR__ . '/assets/latex-math.js' => 'assets/plugins/latex-math.js',
        ];
    }
}
