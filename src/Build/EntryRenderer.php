<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Render\MarkdownRenderer;

final class EntryRenderer
{
    public const string ENTRY_TEMPLATE = __DIR__ . '/../Render/Template/entry.php';

    private MarkdownRenderer $markdownRenderer;

    public function __construct(
        private ?BuildCache $cache = null,
    ) {
        $this->markdownRenderer = new MarkdownRenderer();
    }

    public function render(SiteConfig $siteConfig, Entry $entry, ?Navigation $navigation = null): string
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($entry->sourceFilePath());
            if ($cached !== null) {
                return $cached;
            }
        }

        $content = $this->markdownRenderer->render($entry->body());
        $html = $this->renderTemplate($siteConfig, $entry, $content, $navigation);

        $this->cache?->set($entry->sourceFilePath(), $html);

        return $html;
    }

    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content, ?Navigation $navigation): string
    {
        $siteTitle = $siteConfig->title;
        $entryTitle = $entry->title;
        $date = $entry->date?->format('Y-m-d') ?? '';
        $author = implode(', ', $entry->authors);
        $collection = $entry->collection;
        $nav = $navigation;

        ob_start();
        require self::ENTRY_TEMPLATE;
        return ob_get_clean();
    }
}
