<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Render\MarkdownRenderer;

final class EntryRenderer
{
    private const string ENTRY_TEMPLATE = __DIR__ . '/../Render/Template/entry.php';

    private MarkdownRenderer $markdownRenderer;

    public function __construct()
    {
        $this->markdownRenderer = new MarkdownRenderer();
    }

    public function render(SiteConfig $siteConfig, Entry $entry): string
    {
        $content = $this->markdownRenderer->render($entry->body());
        return $this->renderTemplate($siteConfig, $entry, $content);
    }

    private function renderTemplate(SiteConfig $siteConfig, Entry $entry, string $content): string
    {
        $siteTitle = $siteConfig->title;
        $entryTitle = $entry->title;
        $date = $entry->date?->format('Y-m-d') ?? '';
        $author = implode(', ', $entry->authors);
        $collection = $entry->collection;

        ob_start();
        require self::ENTRY_TEMPLATE;
        return ob_get_clean();
    }
}
