<?php

declare(strict_types=1);

namespace App\Processor\Toc;

use App\Content\Model\Entry;
use App\Processor\ContentProcessorInterface;

use function htmlspecialchars;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function strip_tags;
use function trim;

/**
 * Injects `id` attributes into heading tags and collects table of contents data.
 *
 * Runs on the final rendered HTML (after markdown, syntax highlighting, etc.).
 * Heading IDs are slugified from the heading text; duplicates get a numeric suffix.
 * Headings that already have an `id` attribute are left unchanged but still collected.
 */
final class TocProcessor implements ContentProcessorInterface, TocAwareInterface
{
    /** @var list<array{id: string, text: string, level: int}> */
    private array $toc = [];

    public function process(string $content, Entry $entry): string
    {
        $this->toc = [];
        $slugCounts = [];

        return (string) preg_replace_callback(
            '/<(h[1-6])(\s[^>]*)?>(.+?)<\/\1>/si',
            function (array $matches) use (&$slugCounts): string {
                $tag = $matches[1];
                $attrs = $matches[2] ?? '';
                $inner = $matches[3];
                $level = (int) $tag[1];
                $text = trim(strip_tags($inner));

                // If already has an id, collect but don't modify
                if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/', $attrs, $idMatch)) {
                    $this->toc[] = ['id' => $idMatch[1], 'text' => $text, 'level' => $level];
                    return $matches[0];
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
                return "<$tag$attrs id=\"$escapedId\">$inner</$tag>";
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
}
