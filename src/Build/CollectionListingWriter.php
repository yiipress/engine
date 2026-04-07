<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;

use RuntimeException;

use function count;

final readonly class CollectionListingWriter
{
    public function __construct(
        private TemplateResolver $templateResolver,
        private ?AssetFingerprintManifest $assetManifest = null,
    ) {}

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
        $pageCount = 0;

        foreach ($pages as $pageIndex => $pageEntries) {
            $pageNumber = $pageIndex + 1;

            $currentPermalink = $pageNumber === 1
                ? '/' . $collection->name . '/'
                : '/' . $collection->name . '/page/' . $pageNumber . '/';
            $rootPath = RelativePathHelper::rootPath($currentPermalink);

            $entryData = [];
            foreach ($pageEntries as $entry) {
                $entryData[] = [
                    'title' => $entry->title,
                    'url' => RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection), $rootPath),
                    'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
                    'dateISO' => $entry->date?->format('Y-m-d') ?? '',
                    'draft' => $entry->draft,
                    'summary' => $entry->summary(),
                ];
            }

            $pagination = [
                'currentPage' => $pageNumber,
                'totalPages' => $totalPages,
                'previousUrl' => $this->resolvePageUrl($collection->name, $pageNumber - 1, $totalPages, $rootPath),
                'nextUrl' => $this->resolvePageUrl($collection->name, $pageNumber + 1, $totalPages, $rootPath),
            ];

            $html = $this->renderPage($siteConfig, $collection, $entryData, $pagination, $navigation, $rootPath, $currentPermalink);

            $dir = $pageNumber === 1
                ? $outputDir . '/' . $collection->name
                : $outputDir . '/' . $collection->name . '/page/' . $pageNumber;

            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            file_put_contents($dir . '/index.html', $html);
            $pageCount++;
        }

        return $pageCount;
    }

    /**
     * @param list<array{title: string, url: string, date: string, dateISO: string, draft: bool, summary: string}> $entries
     * @param array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
     */
    private function renderPage(
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
        array $pagination,
        ?Navigation $navigation,
        string $rootPath,
        string $permalink,
    ): string {
        $siteTitle = $siteConfig->title;
        $collectionTitle = $collection->title;
        $collectionName = $collection->name;
        $nav = $navigation;
        $templateContext = new TemplateContext($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $partial = $templateContext->partial(...);
        $metaTags = MetaTagsBuilder::forPage($siteConfig, $collectionTitle, $collection->description, $permalink);
        $assetManifest = $this->assetManifest;
        $search = $siteConfig->search !== null;
        $searchResults = $siteConfig->search?->results ?? 10;

        ob_start();
        require $this->templateResolver->resolve('collection_listing');
        return $templateContext->rewriteHtml((string) ob_get_clean(), $rootPath);
    }

    private function resolvePageUrl(string $collectionName, int $pageNumber, int $totalPages, string $rootPath): string
    {
        if ($pageNumber < 1 || $pageNumber > $totalPages) {
            return '';
        }

        if ($pageNumber === 1) {
            return $rootPath . $collectionName . '/';
        }

        return $rootPath . $collectionName . '/page/' . $pageNumber . '/';
    }
}
