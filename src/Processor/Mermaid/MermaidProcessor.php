<?php

declare(strict_types=1);

namespace App\Processor\Mermaid;

use App\Content\Model\Entry;
use App\Processor\AssetProcessorInterface;
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
final readonly class MermaidProcessor implements ContentProcessorInterface, AssetProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        return (string) preg_replace_callback(
            '/<pre><code class="language-mermaid">(.+?)<\/code><\/pre>/s',
            static function (array $matches): string {
                $diagramCode = strip_tags(htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
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
    <link rel="stylesheet" href="assets/plugins/mermaid.css">
    <script defer src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <script>
        var mermaidThemes = {
            light: {
                primaryColor: '#e8f0fe',
                primaryTextColor: '#111111',
                primaryBorderColor: '#111111',
                lineColor: '#111111',
                secondaryColor: '#f3f4f6',
                tertiaryColor: '#f9fafb',
                nodeTextColor: '#111111',
                edgeLabelBackground: '#ffffff',
                clusterBkg: '#f3f4f6',
                clusterBorder: '#111111',
                titleColor: '#111111'
            },
            dark: {
                primaryColor: '#374151',
                primaryTextColor: '#f0f0f0',
                primaryBorderColor: '#6b7280',
                lineColor: '#f0f0f0',
                secondaryColor: '#2d3748',
                tertiaryColor: '#1f2937',
                nodeTextColor: '#f0f0f0',
                edgeLabelBackground: '#374151',
                clusterBkg: '#1f2937',
                clusterBorder: '#4b5563',
                titleColor: '#f0f0f0'
            }
        };

        function getMermaidTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }

        function initializeMermaid() {
            mermaid.initialize({
                startOnLoad: false,
                theme: 'base',
                securityLevel: 'strict',
                themeVariables: mermaidThemes[getMermaidTheme()]
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
