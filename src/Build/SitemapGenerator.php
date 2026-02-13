<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use samdark\sitemap\Sitemap;

final class SitemapGenerator
{
    /**
     * @param array<string, Collection> $collections
     * @param array<string, list<Entry>> $entriesByCollection
     */
    public function generate(
        SiteConfig $siteConfig,
        array $collections,
        array $entriesByCollection,
        string $outputDir,
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
                $permalink = $entry->permalink !== ''
                    ? $entry->permalink
                    : str_replace(
                        [':collection', ':slug'],
                        [$collectionName, $entry->slug],
                        $collection->permalink,
                    );

                $lastmod = $entry->date?->getTimestamp();

                $sitemap->addItem(
                    $baseUrl . $permalink,
                    $lastmod ?? null,
                );
            }
        }

        $sitemap->write();
    }
}
