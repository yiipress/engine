<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\PermalinkResolver;
use RuntimeException;

use function array_chunk;
use function count;
use function rtrim;

final readonly class TaxonomyPageWriter
{
    public function __construct(
        private TemplateResolver $templateResolver,
        private ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    /**
     * @param array<string, array<string, list<Entry>>> $taxonomyData
     * @param array<string, Collection> $collections
     */
    public function write(
        SiteConfig $siteConfig,
        array $taxonomyData,
        array $collections,
        string $outputDir,
        ?Navigation $navigation = null,
        bool $noWrite = false,
    ): int {
        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest, $siteConfig->minify);
        $pageCount = 0;

        foreach ($taxonomyData as $taxonomyName => $terms) {
            if ($terms === []) {
                continue;
            }

            // PHP converts numeric string keys to integers - cast back to strings
            $termNames = array_map(strval(...), array_keys($terms));
            $this->writeIndexPage($renderer, $siteConfig, $taxonomyName, $termNames, $outputDir, $navigation, $noWrite);
            $pageCount++;

            foreach ($terms as $term => $entries) {
                $pageCount += $this->writeTermPages($renderer, $siteConfig, $taxonomyName, (string) $term, $entries, $collections, $outputDir, $navigation, $noWrite);
            }
        }

        return $pageCount;
    }

    /**
     * @param list<string> $terms
     */
    private function writeIndexPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        string $taxonomyName,
        array $terms,
        string $outputDir,
        ?Navigation $navigation,
        bool $noWrite,
    ): void {
        $rootPath = UrlResolver::rootPath('/' . $taxonomyName . '/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);
        $taxonomyLabel = $uiViewData->ui->taxonomyLabel($taxonomyName);
        $html = $renderer->render('taxonomy_index', [
            'siteTitle' => $siteConfig->title,
            'data' => $siteConfig->data,
            'taxonomyName' => $taxonomyName,
            'terms' => $terms,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $taxonomyLabel, $siteConfig->description, '/' . $taxonomyName . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        if (!$noWrite) {
            $dir = $outputDir . '/' . $taxonomyName;
            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            FileWriter::write($dir . '/index.html', $html);
        }
    }

    /**
     * @param list<Entry> $entries
     * @param array<string, Collection> $collections
     */
    private function writeTermPages(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        string $taxonomyName,
        string $term,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation,
        bool $noWrite,
    ): int {
        $perPage = $siteConfig->entriesPerPage;
        if ($perPage <= 0) {
            $perPage = count($entries) ?: 1;
        }

        $pages = array_chunk($entries, $perPage);
        $totalPages = count($pages);
        $pageCount = 0;
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);
        $taxonomyLabel = $uiViewData->ui->taxonomyLabel($taxonomyName);

        foreach ($pages as $pageIndex => $pageEntries) {
            $pageNumber = $pageIndex + 1;
            $permalink = $this->termPagePermalink($taxonomyName, $term, $pageNumber);
            $rootPath = UrlResolver::rootPath($permalink);

            $entryData = [];
            foreach ($pageEntries as $entry) {
                $collection = $collections[$entry->collection] ?? null;
                $url = $collection !== null
                    ? UrlResolver::sitePath(PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n), $rootPath)
                    : '#';

                $entryData[] = [
                    'title' => $entry->title,
                    'url' => $url,
                    'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
                ];
            }

            $pagination = [
                'currentPage' => $pageNumber,
                'totalPages' => $totalPages,
                'previousUrl' => $this->resolveTermPageUrl($taxonomyName, $term, $pageNumber - 1, $totalPages, $rootPath),
                'nextUrl' => $this->resolveTermPageUrl($taxonomyName, $term, $pageNumber + 1, $totalPages, $rootPath),
            ];

            $html = $renderer->render('taxonomy_term', [
                'siteTitle' => $siteConfig->title,
                'data' => $siteConfig->data,
                'taxonomyName' => $taxonomyName,
                'term' => $term,
                'entries' => $entryData,
                'pagination' => $pagination,
                'nav' => $navigation,
                'rootPath' => $rootPath,
                'language' => $siteConfig->defaultLanguage,
                'metaTags' => MetaTagsBuilder::forPage($siteConfig, $term . ' — ' . $taxonomyLabel, $siteConfig->description, $permalink),
                'search' => $siteConfig->search !== null,
                'searchResults' => $siteConfig->search?->results ?? 10,
            ] + $uiViewData->toArray(), $rootPath);

            if (!$noWrite) {
                $dir = $outputDir . rtrim($permalink, '/');
                if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
                }

                FileWriter::write($dir . '/index.html', $html);
            }

            $pageCount++;
        }

        return $pageCount;
    }

    private function termPagePermalink(string $taxonomyName, string $term, int $pageNumber): string
    {
        if ($pageNumber === 1) {
            return '/' . $taxonomyName . '/' . $term . '/';
        }

        return '/' . $taxonomyName . '/' . $term . '/page/' . $pageNumber . '/';
    }

    private function resolveTermPageUrl(string $taxonomyName, string $term, int $pageNumber, int $totalPages, string $rootPath): string
    {
        if ($pageNumber < 1 || $pageNumber > $totalPages) {
            return '';
        }

        return UrlResolver::sitePath($this->termPagePermalink($taxonomyName, $term, $pageNumber), $rootPath);
    }
}
