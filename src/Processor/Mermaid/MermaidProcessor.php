<?php

declare(strict_types=1);

namespace YiiPress\Processor\Mermaid;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\AssetProcessorInterface;
use YiiPress\Processor\ContentProcessorInterface;

use function str_contains;

/**
 * Converts Mermaid diagram code blocks into div elements for client-side rendering.
 *
 * Transforms:
 *   <pre><code class="language-mermaid">graph TD; A--&gt;B;</code></pre>
 * Into:
 *   <div class="mermaid" tabindex="0">graph TD; A--&gt;B;</div>
 *
 * The diagram source stays HTML-escaped, so the browser hands the original text to Pele,
 * which renders it as SVG themed through CSS variables.
 */
final readonly class MermaidProcessor implements ContentProcessorInterface, AssetProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        if (!str_contains($content, 'language-mermaid')) {
            return $content;
        }

        return (string) preg_replace(
            '/<pre><code class="language-mermaid">(.+?)<\/code><\/pre>/s',
            '<div class="mermaid" tabindex="0">$1</div>',
            $content,
        );
    }

    public function headAssets(string $processedContent): string
    {
        if (!str_contains($processedContent, '<div class="mermaid"')) {
            return '';
        }

        return <<<'HTML'
    <link rel="stylesheet" href="assets/plugins/mermaid.css">
    <script defer src="https://cdn.jsdelivr.net/npm/pele@1.12.0/dist/pele.global.js" integrity="sha384-c+QpxCAcAD72seekPP6lAUNHjqe/wUv+S2pk2yBo4ECNKMxym7WFtKKoR5xMtyhz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.mermaid').forEach(function (diagram) {
                pele.mount(diagram, diagram.textContent);
            });
        });
    </script>
HTML;
    }

    public function assetFiles(): array
    {
        return [
            __DIR__ . '/assets/mermaid.css' => 'assets/plugins/mermaid.css',
        ];
    }
}
