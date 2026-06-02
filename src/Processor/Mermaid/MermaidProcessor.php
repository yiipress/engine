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
        if (!str_contains($content, 'language-mermaid')) {
            return $content;
        }

        return (string) preg_replace_callback(
            '/<pre><code class="language-mermaid">(.+?)<\/code><\/pre>/s',
            static function (array $matches): string {
                $diagramCode = strip_tags(htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
                return '<div class="mermaid" tabindex="0">' . $diagramCode . '</div>';
            },
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
        var activeMermaidDiagram = null;
        var collapseMermaidTimer = null;
        var lastMermaidPointer = null;
        var mermaidInteractionTracking = false;

        function getMermaidTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }

        function enhanceMermaidDiagrams() {
            document.querySelectorAll('.mermaid').forEach(function (diagram) {
                if (diagram.dataset.mermaidEnhanced === 'true') {
                    return;
                }

                diagram.dataset.mermaidEnhanced = 'true';
                updateMermaidOverflow(diagram);

                diagram.addEventListener('pointerenter', function () {
                    expandMermaidDiagram(diagram);
                });

                diagram.addEventListener('focus', function () {
                    if (diagram.classList.contains('mermaid-overflowing')) {
                        centerMermaidDiagramAfterLayout(diagram);
                    }
                });

                diagram.addEventListener('pointerleave', function (event) {
                    scheduleMermaidCollapse(diagram, { x: event.clientX, y: event.clientY });
                });
            });

            ensureMermaidInteractionTracking();
        }

        function expandMermaidDiagram(diagram) {
            if (!diagram.classList.contains('mermaid-overflowing')) {
                return;
            }

            if (activeMermaidDiagram && activeMermaidDiagram !== diagram) {
                collapseMermaidDiagram(activeMermaidDiagram);
            }

            activeMermaidDiagram = diagram;
            diagram.classList.add('is-expanded');
            centerMermaidDiagramAfterLayout(diagram);
        }

        function collapseMermaidDiagram(diagram) {
            if (!diagram) {
                return;
            }

            diagram.classList.remove('is-expanded');
            if (activeMermaidDiagram === diagram) {
                activeMermaidDiagram = null;
            }
        }

        function scheduleMermaidCollapse(diagram, pointer) {
            window.clearTimeout(collapseMermaidTimer);
            collapseMermaidTimer = window.setTimeout(function () {
                if (activeMermaidDiagram !== diagram) {
                    return;
                }

                if (isPointerInsideMermaid(diagram, pointer || lastMermaidPointer, 8)) {
                    return;
                }

                collapseMermaidDiagram(diagram);
            }, 160);
        }

        function isPointerInsideMermaid(diagram, pointer, tolerance) {
            var rect;

            if (!pointer) {
                return false;
            }

            rect = diagram.getBoundingClientRect();

            return pointer.x >= rect.left - tolerance
                && pointer.x <= rect.right + tolerance
                && pointer.y >= rect.top - tolerance
                && pointer.y <= rect.bottom + tolerance;
        }

        function ensureMermaidInteractionTracking() {
            if (mermaidInteractionTracking) {
                return;
            }

            mermaidInteractionTracking = true;
            window.addEventListener('pointermove', function (event) {
                lastMermaidPointer = { x: event.clientX, y: event.clientY };
                if (!activeMermaidDiagram) {
                    return;
                }

                if (isPointerInsideMermaid(activeMermaidDiagram, lastMermaidPointer, 8)) {
                    window.clearTimeout(collapseMermaidTimer);
                    return;
                }

                scheduleMermaidCollapse(activeMermaidDiagram, lastMermaidPointer);
            }, { passive: true });

            window.addEventListener('scroll', function () {
                collapseMermaidDiagram(activeMermaidDiagram);
            }, { passive: true });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    collapseMermaidDiagram(activeMermaidDiagram);
                }
            });
        }

        function updateMermaidOverflow(diagram) {
            if (diagram.scrollWidth > diagram.clientWidth + 1) {
                diagram.classList.add('mermaid-overflowing');
                centerMermaidDiagram(diagram);
                return;
            }

            diagram.classList.remove('mermaid-overflowing', 'is-expanded');
        }

        function centerMermaidDiagram(diagram) {
            diagram.scrollLeft = Math.max(0, (diagram.scrollWidth - diagram.clientWidth) / 2);
        }

        function centerMermaidDiagramAfterLayout(diagram) {
            window.requestAnimationFrame(function () {
                centerMermaidDiagram(diagram);
            });
            window.setTimeout(function () {
                centerMermaidDiagram(diagram);
            }, 180);
        }

        function initializeMermaid() {
            mermaid.initialize({
                startOnLoad: false,
                theme: 'base',
                securityLevel: 'strict',
                flowchart: {
                    useMaxWidth: false
                },
                themeVariables: mermaidThemes[getMermaidTheme()]
            });
            Promise.resolve(mermaid.run({
                querySelector: '.mermaid'
            })).then(function () {
                enhanceMermaidDiagrams();
                window.addEventListener('resize', function () {
                    document.querySelectorAll('.mermaid').forEach(updateMermaidOverflow);
                });
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
