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
        $pageCount = 0;

        foreach ($taxonomyData as $taxonomyName => $terms) {
            if ($terms === []) {
                continue;
            }

            // PHP converts numeric string keys to integers - cast back to strings
            $termNames = array_map(strval(...), array_keys($terms));
            $this->writeIndexPage($siteConfig, $taxonomyName, $termNames, $outputDir, $navigation);
            $pageCount++;

            foreach ($terms as $term => $entries) {
                $this->writeTermPage($siteConfig, $taxonomyName, (string) $term, $entries, $collections, $outputDir, $navigation);
                $pageCount++;
            }
        }

        return $pageCount;
    }

    /**
     * @param list<string> $terms
     */
    private function writeIndexPage(
        SiteConfig $siteConfig,
        string $taxonomyName,
        array $terms,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $templateContext = new TemplateContext($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $partial = $templateContext->partial(...);
        $rootPath = RelativePathHelper::rootPath('/' . $taxonomyName . '/');
        $metaTags = MetaTagsBuilder::forPage($siteConfig, ucfirst($taxonomyName), $siteConfig->description, '/' . $taxonomyName . '/');
        $assetManifest = $this->assetManifest;
        $search = $siteConfig->search !== null;
        $searchResults = $siteConfig->search?->results ?? 10;

        ob_start();
        require $this->templateResolver->resolve('taxonomy_index');
        $html = $templateContext->rewriteHtml((string) ob_get_clean(), $rootPath);

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
        SiteConfig $siteConfig,
        string $taxonomyName,
        string $term,
        array $entries,
        array $collections,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $nav = $navigation;
        $templateContext = new TemplateContext($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $partial = $templateContext->partial(...);
        $rootPath = RelativePathHelper::rootPath('/' . $taxonomyName . '/' . $term . '/');
        $metaTags = MetaTagsBuilder::forPage($siteConfig, $term . ' — ' . ucfirst($taxonomyName), $siteConfig->description, '/' . $taxonomyName . '/' . $term . '/');
        $assetManifest = $this->assetManifest;
        $search = $siteConfig->search !== null;
        $searchResults = $siteConfig->search?->results ?? 10;

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

        ob_start();
        require $this->templateResolver->resolve('taxonomy_term');
        $html = $templateContext->rewriteHtml((string) ob_get_clean(), $rootPath);

        $dir = $outputDir . '/' . $taxonomyName . '/' . $term;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
