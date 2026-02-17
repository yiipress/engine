<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;

final class DateArchiveWriter
{
    public function __construct(private TemplateResolver $templateResolver) {}

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
        $byYear = [];
        $byMonth = [];

        foreach ($entries as $entry) {
            if ($entry->date === null) {
                continue;
            }

            $year = $entry->date->format('Y');
            $month = $entry->date->format('m');

            $byYear[$year][] = $entry;
            $byMonth[$year][$month][] = $entry;
        }

        if ($byYear === []) {
            return 0;
        }

        krsort($byYear);

        $baseUrl = rtrim($siteConfig->baseUrl, '/');
        $pageCount = 0;

        foreach ($byYear as $year => $yearEntries) {
            $this->writeYearlyPage(
                $siteConfig,
                $collection,
                (string) $year,
                $yearEntries,
                array_keys($byMonth[$year]),
                $baseUrl,
                $outputDir,
                $navigation,
            );
            $pageCount++;

            $months = $byMonth[$year];
            krsort($months);

            foreach ($months as $month => $monthEntries) {
                $this->writeMonthlyPage(
                    $siteConfig,
                    $collection,
                    (string) $year,
                    (string) $month,
                    $monthEntries,
                    $baseUrl,
                    $outputDir,
                    $navigation,
                );
                $pageCount++;
            }
        }

        return $pageCount;
    }

    /**
     * @param list<Entry> $entries
     * @param list<string> $months
     */
    private function writeYearlyPage(
        SiteConfig $siteConfig,
        Collection $collection,
        string $year,
        array $entries,
        array $months,
        string $baseUrl,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $collectionName = $collection->name;
        $collectionTitle = $collection->title;
        $nav = $navigation;
        $partial = (new TemplateContext($this->templateResolver, $siteConfig->theme))->partial(...);

        rsort($months);

        $entryData = [];
        foreach ($entries as $entry) {
            $entryData[] = [
                'title' => $entry->title,
                'url' => $baseUrl . PermalinkResolver::resolve($entry, $collection),
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        ob_start();
        require $this->templateResolver->resolve('archive_yearly');
        $html = ob_get_clean();

        $dir = $outputDir . '/' . $collection->name . '/' . $year;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($dir . '/index.html', $html);
    }

    /**
     * @param list<Entry> $entries
     */
    private function writeMonthlyPage(
        SiteConfig $siteConfig,
        Collection $collection,
        string $year,
        string $month,
        array $entries,
        string $baseUrl,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $siteTitle = $siteConfig->title;
        $collectionName = $collection->name;
        $collectionTitle = $collection->title;
        $nav = $navigation;
        $partial = (new TemplateContext($this->templateResolver, $siteConfig->theme))->partial(...);

        $monthName = date('F', mktime(0, 0, 0, (int) $month, 1));

        $entryData = [];
        foreach ($entries as $entry) {
            $entryData[] = [
                'title' => $entry->title,
                'url' => $baseUrl . PermalinkResolver::resolve($entry, $collection),
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        ob_start();
        require $this->templateResolver->resolve('archive_monthly');
        $html = ob_get_clean();

        $dir = $outputDir . '/' . $collection->name . '/' . $year . '/' . $month;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
