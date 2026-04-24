<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use samdark\sitemap\Sitemap;

use function sys_get_temp_dir;

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
        bool $noWrite = false,
    ): void {
        $sitemapPath = ($noWrite ? sys_get_temp_dir() : $outputDir) . '/sitemap.xml';
        $baseUrl = rtrim($siteConfig->baseUrl, '/');

        $sitemap = new Sitemap($sitemapPath);
        $sitemap->setBufferSize(1000);
        $sitemap->setUseIndent(false);

        $sitemap->addItem($baseUrl . '/');

        foreach ($collections as $collectionName => $collection) {
            if ($collection->listing) {
                $sitemap->addItem($baseUrl . '/' . $collectionName . '/');
            }

            $entries = $entriesByCollection[$collectionName] ?? [];
            foreach ($entries as $entry) {
                $permalink = PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n);
                $lastmod = $entry->date?->getTimestamp();

                $sitemap->addItem(
                    $baseUrl . $permalink,
                    $lastmod ?? null,
                );
            }
        }

        foreach ($standalonePages as $page) {
            $basePermalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $permalink = PermalinkResolver::applyLanguagePrefix($basePermalink, $page->language, $siteConfig->i18n);
            $lastmod = $page->date?->getTimestamp();
            $sitemap->addItem($baseUrl . $permalink, $lastmod ?? null);
        }

        if ($authors !== []) {
            $sitemap->addItem($baseUrl . '/authors/');
            foreach ($authors as $slug => $author) {
                $sitemap->addItem($baseUrl . '/authors/' . $slug . '/');
            }
        }

        if (!$noWrite) {
            $sitemap->write();
        }
    }
}
