<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Render\MarkdownRenderer;

final class EntryRenderer
{
    public const string ENTRY_TEMPLATE = __DIR__ . '/../Render/Template/entry.php';

    private ?MarkdownRenderer $markdownRenderer = null;
    private ?MarkdownConfig $lastConfig = null;

    public function __construct(
        private ?BuildCache $cache = null,
        private string $contentDir = '',
    ) {}

    private function markdownRenderer(MarkdownConfig $config): MarkdownRenderer
    {
        if ($this->markdownRenderer === null || $this->lastConfig !== $config) {
            $this->markdownRenderer = new MarkdownRenderer($config);
            $this->lastConfig = $config;
        }

        return $this->markdownRenderer;
    }

    public function render(
        SiteConfig $siteConfig,
        Entry $entry,
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
    ): string {
        if ($this->cache !== null) {
            $cached = $this->cache->get($entry->sourceFilePath());
            if ($cached !== null) {
                return $cached;
            }
        }

        $body = $entry->body();
        if ($crossRefResolver !== null) {
            $body = $crossRefResolver->withCurrentDir($this->resolveContentDir($entry))->resolve($body);
        }
        $content = $this->markdownRenderer($siteConfig->markdown)->render($body);
        $html = $this->renderTemplate($siteConfig, $entry, $content, $navigation);

        $this->cache?->set($entry->sourceFilePath(), $html);

        return $html;
    }

    private function resolveContentDir(Entry $entry): string
    {
        $relative = substr($entry->sourceFilePath(), strlen($this->contentDir) + 1);
        $dir = dirname($relative);
        return $dir === '.' ? '' : $dir;
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
