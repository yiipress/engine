<?php

declare(strict_types=1);

namespace App\Processor\Mermaid;

use App\Content\Model\Entry;
use App\Processor\AssetAwareProcessorInterface;
use App\Processor\ContentProcessorInterface;

/**
 * Converts Mermaid diagram code blocks into div elements for client-side rendering.
 *
 * Transforms:
 *   <pre><code class="language-mermaid">graph TD; A-->B;</code></pre>
 * Into:
 *   <div class="mermaid">graph TD; A-->B;</div>
 *
 * Mermaid.js (loaded in the template) will render the diagram as SVG.
 */
final class MermaidProcessor implements ContentProcessorInterface, AssetAwareProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        return (string) preg_replace_callback(
            '/<pre><code class="language-mermaid">(.+?)<\/code><\/pre>/s',
            static function (array $matches): string {
                $diagramCode = htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
                return '<div class="mermaid">' . $diagramCode . '</div>';
            },
            $content,
        );
    }

    public function headAssets(string $processedContent): string
    {
        if (!str_contains($processedContent, '<div class="mermaid">')) {
            return '';
        }

        return <<<'HTML'
    <link rel="stylesheet" href="/assets/plugins/mermaid.css">
    <script defer src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <script>
        function getMermaidTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default';
        }

        function initializeMermaid() {
            mermaid.initialize({
                startOnLoad: false,
                theme: getMermaidTheme(),
                securityLevel: 'loose'
            });
            mermaid.run({
                querySelector: '.mermaid'
            });
        }

        document.addEventListener('DOMContentLoaded', initializeMermaid);
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
