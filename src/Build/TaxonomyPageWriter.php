<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;

final class TaxonomyPageWriter
{
    public function __construct(private readonly TemplateResolver $templateResolver) {}

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

            $this->writeIndexPage($siteConfig, $taxonomyName, array_keys($terms), $outputDir, $navigation);
            $pageCount++;

            foreach ($terms as $term => $entries) {
                $this->writeTermPage($siteConfig, $taxonomyName, $term, $entries, $collections, $outputDir, $navigation);
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
        $partial = (new TemplateContext($this->templateResolver, $siteConfig->theme))->partial(...);
        $rootPath = RelativePathHelper::rootPath('/' . $taxonomyName . '/');

        ob_start();
        require $this->templateResolver->resolve('taxonomy_index');
        $html = ob_get_clean();

        $dir = $outputDir . '/' . $taxonomyName;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
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
        $partial = (new TemplateContext($this->templateResolver, $siteConfig->theme))->partial(...);
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
                'date' => $entry->date?->format('Y-m-d') ?? '',
            ];
        }

        $entries = $entryData;

        ob_start();
        require $this->templateResolver->resolve('taxonomy_term');
        $html = ob_get_clean();

        $dir = $outputDir . '/' . $taxonomyName . '/' . $term;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
