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
        if (!str_contains($processedContent, '<span class="math')) {
            return '';
        }

        return <<<'HTML'
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" integrity="sha384-nB0miv6/jRmo5UMMR1wu3Gz6NLsoTkbqJghGIsx//Rlm+ZU03BU6SQNC66uf4l5+" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" integrity="sha384-7zkQWkzuo3B5mTepMUcHkMB5jZaolc2xDwL6VFqjFALcbeS9Ggm/Yr2r3Dy4lfFg" crossorigin="anonymous"></script>
    <script defer src="assets/plugins/latex-math.js" crossorigin="anonymous"></script>
HTML;
    }

    public function assetFiles(): array
    {
        return [
            __DIR__ . '/assets/latex-math.js' => 'assets/plugins/latex-math.js',
        ];
    }
}
