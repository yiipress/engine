<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;

final class SearchIndexGenerator
{
    /**
     * @param array<string, Collection> $collections
     * @param array<string, list<Entry>> $entriesByCollection
     * @param list<Entry> $standalonePages
     */
    public function generate(
        SiteConfig $siteConfig,
        array $collections,
        array $entriesByCollection,
        string $outputDir,
        array $standalonePages = [],
        bool $noWrite = false,
    ): void {
        if ($siteConfig->search === null) {
            return;
        }

        $fullText = $siteConfig->search->fullText;
        $items = [];

        foreach ($collections as $collectionName => $collection) {
            foreach ($entriesByCollection[$collectionName] ?? [] as $entry) {
                $permalink = PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n);
                $item = [
                    'title' => $entry->title,
                    'url' => ltrim($permalink, '/'),
                    'summary' => $entry->summary(),
                    'tags' => $entry->tags,
                ];
                if ($fullText) {
                    $item['body'] = Entry::stripMarkdown($entry->body());
                }
                $items[] = $item;
            }
        }

        foreach ($standalonePages as $page) {
            $basePermalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $permalink = PermalinkResolver::applyLanguagePrefix($basePermalink, $page->language, $siteConfig->i18n);
            $item = [
                'title' => $page->title,
                'url' => ltrim($permalink, '/'),
                'summary' => $page->summary(),
                'tags' => $page->tags,
            ];
            if ($fullText) {
                $item['body'] = Entry::stripMarkdown($page->body());
            }
            $items[] = $item;
        }

        $json = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$noWrite) {
            file_put_contents($outputDir . '/search-index.json', $json);
        }
    }
}
