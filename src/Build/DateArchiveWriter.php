<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\PermalinkResolver;
use RuntimeException;

final readonly class DateArchiveWriter
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
        int $workerCount = 1,
    ): int {
        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest);
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

        $tasks = [[
            'type' => 'index',
            'years' => array_keys($byYear),
        ]];

        foreach ($byYear as $year => $yearEntries) {
            $tasks[] = [
                'type' => 'year',
                'year' => (string) $year,
                'entries' => $yearEntries,
                'months' => array_keys($byMonth[$year]),
            ];

            $months = $byMonth[$year];
            krsort($months);

            foreach ($months as $month => $monthEntries) {
                $tasks[] = [
                    'type' => 'month',
                    'year' => (string) $year,
                    'month' => (string) $month,
                    'entries' => $monthEntries,
                ];
            }
        }

        $taskRunner = new ParallelTaskRunner();

        return $taskRunner->run($tasks, $workerCount, function (array $task) use ($renderer, $siteConfig, $collection, $outputDir, $navigation): int {
            if ($task['type'] === 'index') {
                $this->writeArchiveIndexPage(
                    $renderer,
                    $siteConfig,
                    $collection,
                    $task['years'],
                    $outputDir,
                    $navigation,
                );

                return 1;
            }

            if ($task['type'] === 'year') {
                $this->writeYearlyPage(
                    $renderer,
                    $siteConfig,
                    $collection,
                    $task['year'],
                    $task['entries'],
                    $task['months'],
                    $outputDir,
                    $navigation,
                );

                return 1;
            }

            $this->writeMonthlyPage(
                $renderer,
                $siteConfig,
                $collection,
                $task['year'],
                $task['month'],
                $task['entries'],
                $outputDir,
                $navigation,
            );

            return 1;
        });
    }

    /**
     * @param list<string> $years
     */
    private function writeArchiveIndexPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        Collection $collection,
        array $years,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $rootPath = RelativePathHelper::rootPath('/' . $collection->name . '/archive/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);
        $html = $renderer->render('archive_index', [
            'siteTitle' => $siteConfig->title,
            'collectionName' => $collection->name,
            'collectionTitle' => $collection->title,
            'years' => $years,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $collection->title . ' ' . $uiViewData->ui->get('archive'), $siteConfig->description, '/' . $collection->name . '/archive/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        $dir = $outputDir . '/' . $collection->name . '/archive';
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }

    /**
     * @param list<Entry> $entries
     * @param list<string> $months
     */
    private function writeYearlyPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        Collection $collection,
        string $year,
        array $entries,
        array $months,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $rootPath = RelativePathHelper::rootPath('/' . $collection->name . '/' . $year . '/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);

        rsort($months, SORT_STRING);

        $entryData = [];
        foreach ($entries as $entry) {
            $entryData[] = [
                'title' => $entry->title,
                'url' => RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection), $rootPath),
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        $html = $renderer->render('archive_yearly', [
            'siteTitle' => $siteConfig->title,
            'collectionName' => $collection->name,
            'collectionTitle' => $collection->title,
            'year' => $year,
            'months' => $months,
            'entries' => $entries,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $collection->title . ': ' . $year, $siteConfig->description, '/' . $collection->name . '/' . $year . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        $dir = $outputDir . '/' . $collection->name . '/' . $year;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }

    /**
     * @param list<Entry> $entries
     */
    private function writeMonthlyPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        Collection $collection,
        string $year,
        string $month,
        array $entries,
        string $outputDir,
        ?Navigation $navigation,
    ): void {
        $rootPath = RelativePathHelper::rootPath('/' . $collection->name . '/' . $year . '/' . $month . '/');
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);
        $monthName = $uiViewData->ui->monthName((int) $month);

        $entryData = [];
        foreach ($entries as $entry) {
            $entryData[] = [
                'title' => $entry->title,
                'url' => RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection), $rootPath),
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
            ];
        }

        $entries = $entryData;

        $html = $renderer->render('archive_monthly', [
            'siteTitle' => $siteConfig->title,
            'collectionName' => $collection->name,
            'collectionTitle' => $collection->title,
            'year' => $year,
            'month' => $month,
            'monthName' => $monthName,
            'entries' => $entries,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $collection->title . ': ' . $monthName . ' ' . $year, $siteConfig->description, '/' . $collection->name . '/' . $year . '/' . $month . '/'),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);

        $dir = $outputDir . '/' . $collection->name . '/' . $year . '/' . $month;
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($dir . '/index.html', $html);
    }
}
