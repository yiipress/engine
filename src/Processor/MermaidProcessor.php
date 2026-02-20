<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;

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
final class MermaidProcessor implements ContentProcessorInterface
{
    public function process(string $content, Entry $entry): string
    {
        return preg_replace_callback(
            '/<pre><code class="language-mermaid">(.+?)<\/code><\/pre>/s',
            static function (array $matches): string {
                $diagramCode = htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
                return '<div class="mermaid">' . $diagramCode . '</div>';
            },
            $content,
        );
    }
}
