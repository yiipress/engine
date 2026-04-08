<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use RuntimeException;

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
    ): int {
        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $pageCount = 0;

        foreach ($taxonomyData as $taxonomyName => $terms) {
            if ($terms === []) {
                continue;
            }

            // PHP converts numeric string keys to integers - cast back to strings
            $termNames = array_map(strval(...), array_keys($terms));
            $this->writeIndexPage($renderer, $siteConfig, $taxonomyName, $termNames, $outputDir, $navigation);
            $pageCount++;

            foreach ($terms as $term => $entries) {
                $this->writeTermPage($renderer, $siteConfig, $taxonomyName, (string) $term, $entries, $collections, $outputDir, $navigation);
                $pageCount++;
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
    ): void {
        $rootPath = RelativePathHelper::rootPath('/' . $taxonomyName . '/');
        $html = $renderer->render('taxonomy_index', [
            'siteTitle' => $siteConfig->title,
            'taxonomyName' => $taxonomyName,
            'terms' => $terms,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, ucfirst($taxonomyName), $siteConfig->description, '/' . $taxonomyName . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ], $rootPath);

        $dir = $outputDir . '/' . $taxonomyName;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }

    /**
     * @param list<Entry> $entries
     * @param array<string, Collection> $collections
     */
    private function writeTermPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        string $taxonomyName,
        string $term,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $rootPath = RelativePathHelper::rootPath('/' . $taxonomyName . '/' . $term . '/');

        $entryData = [];
        foreach ($entries as $entry) {
            $collection = $collections[$entry->collection] ?? null;
            $url = $collection !== null
                ? RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection), $rootPath)
                : '#';

            $entryData[] = [
                'title' => $entry->title,
                'url' => $url,
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        $html = $renderer->render('taxonomy_term', [
            'siteTitle' => $siteConfig->title,
            'taxonomyName' => $taxonomyName,
            'term' => $term,
            'entries' => $entries,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $term . ' — ' . ucfirst($taxonomyName), $siteConfig->description, '/' . $taxonomyName . '/' . $term . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ], $rootPath);

        $dir = $outputDir . '/' . $taxonomyName . '/' . $term;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
