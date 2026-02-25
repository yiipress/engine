<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use samdark\sitemap\Sitemap;

final class SitemapGenerator
{
    /**
     * @param array<string, Collection> $collections
     * @param array<string, list<Entry>> $entriesByCollection
     * @param list<Entry> $standalonePages
     * @param array<string, Author> $authors
     */
    public function generate(
        SiteConfig $siteConfig,
        array $collections,
        array $entriesByCollection,
        string $outputDir,
        array $standalonePages = [],
        array $authors = [],
    ): void {
        $sitemapPath = $outputDir . '/sitemap.xml';
        $baseUrl = rtrim($siteConfig->baseUrl, '/');

        $sitemap = new Sitemap($sitemapPath);

        $sitemap->addItem($baseUrl . '/');

        foreach ($collections as $collectionName => $collection) {
            if ($collection->listing) {
                $sitemap->addItem($baseUrl . '/' . $collectionName . '/');
            }

            $entries = $entriesByCollection[$collectionName] ?? [];
            foreach ($entries as $entry) {
                $permalink = PermalinkResolver::resolve($entry, $collection);
                $lastmod = $entry->date?->getTimestamp();

                $permalink = implode('/', array_map('urlencode', explode('/', $permalink)));
                $sitemap->addItem(
                    $baseUrl . $permalink,
                    $lastmod ?? null,
                );
            }
        }

        foreach ($standalonePages as $page) {
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $lastmod = $page->date?->getTimestamp();
            $sitemap->addItem($baseUrl . $permalink, $lastmod ?? null);
        }

        if ($authors !== []) {
            $sitemap->addItem($baseUrl . '/authors/');
            foreach ($authors as $slug => $author) {
                $sitemap->addItem($baseUrl . '/authors/' . $slug . '/');
            }
        }

        $sitemap->write();
    }
}
