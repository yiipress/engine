<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;

final class CollectionListingWriter
{
    public function __construct(private readonly TemplateResolver $templateResolver) {}

    /**
     * @param list<Entry> $entries
     */
    public function write(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
        string $outputDir,
        ?Navigation $navigation = null,
    ): int {
        $perPage = $collection->entriesPerPage;
        if ($perPage <= 0) {
            $perPage = count($entries) ?: 1;
        }

        $pages = $entries !== [] ? array_chunk($entries, $perPage) : [[]];
        $totalPages = count($pages);
        $baseUrl = rtrim($siteConfig->baseUrl, '/');
        $pageCount = 0;

        foreach ($pages as $pageIndex => $pageEntries) {
            $pageNumber = $pageIndex + 1;

            $entryData = [];
            foreach ($pageEntries as $entry) {
                $entryData[] = [
                    'title' => $entry->title,
                    'url' => $baseUrl . PermalinkResolver::resolve($entry, $collection),
                    'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
                    'summary' => $entry->summary(),
                ];
            }

            $pagination = [
                'currentPage' => $pageNumber,
                'totalPages' => $totalPages,
                'previousUrl' => $this->resolvePageUrl($collection->name, $pageNumber - 1, $totalPages),
                'nextUrl' => $this->resolvePageUrl($collection->name, $pageNumber + 1, $totalPages),
            ];

            $html = $this->renderPage($siteConfig, $collection, $entryData, $pagination, $navigation);

            $dir = $pageNumber === 1
                ? $outputDir . '/' . $collection->name
                : $outputDir . '/' . $collection->name . '/page/' . $pageNumber;

            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            file_put_contents($dir . '/index.html', $html);
            $pageCount++;
        }

        return $pageCount;
    }

    /**
     * @param list<array{title: string, url: string, date: string, summary: string}> $entries
     * @param array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
     */
    private function renderPage(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
        array $pagination,
        ?Navigation $navigation,
    ): string {
        $siteTitle = $siteConfig->title;
        $collectionTitle = $collection->title;
        $nav = $navigation;
        $partial = (new TemplateContext($this->templateResolver, $siteConfig->theme))->partial(...);

        ob_start();
        require $this->templateResolver->resolve('collection_listing');
        return ob_get_clean();
    }

    private function resolvePageUrl(string $collectionName, int $pageNumber, int $totalPages): string
    {
        if ($pageNumber < 1 || $pageNumber > $totalPages) {
            return '';
        }

        if ($pageNumber === 1) {
            return '/' . $collectionName . '/';
        }

        return '/' . $collectionName . '/page/' . $pageNumber . '/';
    }
}
