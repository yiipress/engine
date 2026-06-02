<?php

declare(strict_types=1);

namespace YiiPress\Processor\Toc;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorInterface;

use function htmlspecialchars;
use function mb_strtolower;
use function str_contains;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function strip_tags;
use function trim;

/**
 * Injects `id` attributes and permalink anchors into heading tags and collects table of contents data.
 *
 * Runs on the final rendered HTML (after markdown, syntax highlighting, etc.).
 * Heading IDs are slugified from the heading text; duplicates get a numeric suffix.
 * Headings that already have an `id` attribute keep that ID but still get a permalink anchor.
 */
final class TocProcessor implements ContentProcessorInterface, TocAwareInterface
{
    /** @var list<array{id: string, text: string, level: int}> */
    private array $toc = [];

    public function process(string $content, Entry $entry): string
    {
        $this->toc = [];

        if (!str_contains($content, '<h')) {
            return $content;
        }

        $slugCounts = [];

        return (string) preg_replace_callback(
            '/<(h[1-6])(\s[^>]*)?>(.+?)<\/\1>/si',
            function (array $matches) use (&$slugCounts): string {
                $tag = $matches[1];
                $attrs = $matches[2] ?? '';
                $inner = $matches[3];
                $level = (int) $tag[1];
                $innerWithoutAnchor = $this->removeHeaderAnchor($inner);
                $text = trim(strip_tags($innerWithoutAnchor));

                if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/', $attrs, $idMatch)) {
                    $id = $idMatch[1];
                    $this->toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
                    return "<$tag$attrs>" . $this->withHeaderAnchor($inner, $id, $text) . "</$tag>";
                }

                $slug = $this->slugify($text);

                if (isset($slugCounts[$slug])) {
                    $slugCounts[$slug]++;
                    $id = $slug . '-' . $slugCounts[$slug];
                } else {
                    $slugCounts[$slug] = 1;
                    $id = $slug;
                }

                $this->toc[] = ['id' => $id, 'text' => $text, 'level' => $level];

                $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return "<$tag$attrs id=\"$escapedId\">" . $this->withHeaderAnchor($inner, $id, $text) . "</$tag>";
            },
            $content,
        );
    }

    public function getToc(): array
    {
        return $this->toc;
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'heading';
    }

    private function withHeaderAnchor(string $inner, string $id, string $text): string
    {
        if (str_contains($inner, 'header-anchor')) {
            return $inner;
        }

        $escapedId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = $escapedText === '' ? 'Permalink to this heading' : 'Permalink to &quot;' . $escapedText . '&quot;';

        return $inner . '<a class="header-anchor" href="#' . $escapedId . '" aria-label="' . $label . '"></a>';
    }

    private function removeHeaderAnchor(string $inner): string
    {
        return (string) preg_replace('/<a\b[^>]*class=["\'][^"\']*\bheader-anchor\b[^"\']*["\'][^>]*>.*?<\/a>/si', '', $inner);
    }
}
