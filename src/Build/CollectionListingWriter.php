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
        int $workerCount = 1,
    ): int {
        $renderer = new PageTemplateRenderer($this->templateResolver, $siteConfig->theme, $this->assetManifest);
        $perPage = $collection->entriesPerPage;
        if ($perPage <= 0) {
            $perPage = count($entries) ?: 1;
        }

        $pages = $entries !== [] ? array_chunk($entries, $perPage) : [[]];
        $totalPages = count($pages);
        $tasks = [];

        foreach ($pages as $pageIndex => $pageEntries) {
            $pageNumber = $pageIndex + 1;

            $currentPermalink = $pageNumber === 1
                ? '/' . $collection->name . '/'
                : '/' . $collection->name . '/page/' . $pageNumber . '/';
            $rootPath = RelativePathHelper::rootPath($currentPermalink);

            $pagination = [
                'currentPage' => $pageNumber,
                'totalPages' => $totalPages,
                'previousUrl' => $this->resolvePageUrl($collection->name, $pageNumber - 1, $totalPages, $rootPath),
                'nextUrl' => $this->resolvePageUrl($collection->name, $pageNumber + 1, $totalPages, $rootPath),
            ];

            $dir = $pageNumber === 1
                ? $outputDir . '/' . $collection->name
                : $outputDir . '/' . $collection->name . '/page/' . $pageNumber;

            $tasks[] = [
                'entries' => $pageEntries,
                'pagination' => $pagination,
                'rootPath' => $rootPath,
                'permalink' => $currentPermalink,
                'dir' => $dir,
            ];
        }

        $taskRunner = new ParallelTaskRunner();

        return $taskRunner->run($tasks, $workerCount, function (array $task) use ($renderer, $siteConfig, $collection, $navigation): int {
            $html = $this->renderPage(
                $renderer,
                $siteConfig,
                $collection,
                $task['entries'],
                $task['pagination'],
                $navigation,
                $task['rootPath'],
                $task['permalink'],
            );

            if (!is_dir($task['dir']) && !mkdir($task['dir'], 0o755, true) && !is_dir($task['dir'])) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $task['dir']));
            }

            file_put_contents($task['dir'] . '/index.html', $html);

            return 1;
        });
    }

    /**
     * @param list<Entry> $entries
     * @param array{currentPage: int, totalPages: int, previousUrl: string, nextUrl: string} $pagination
     */
    private function renderPage(
        PageTemplateRenderer $renderer,
        SiteConfig $siteConfig,
        Collection $collection,
        array $entries,
        array $pagination,
        ?Navigation $navigation,
        string $rootPath,
        string $permalink,
    ): string {
        $uiViewData = UiViewData::forSite($siteConfig, $this->templateResolver, $siteConfig->theme);

        $entryData = [];
        foreach ($entries as $entry) {
            $entryData[] = [
                'title' => $entry->title,
                'url' => RelativePathHelper::relativize(PermalinkResolver::resolve($entry, $collection, $siteConfig->i18n), $rootPath),
                'date' => $entry->date?->format($siteConfig->dateFormat) ?? '',
                'dateISO' => $entry->date?->format('Y-m-d') ?? '',
                'draft' => $entry->draft,
                'summary' => $entry->summary(),
            ];
        }

        return $renderer->render('collection_listing', [
            'siteTitle' => $siteConfig->title,
            'collectionTitle' => $collection->title,
            'collectionName' => $collection->name,
            'entries' => $entryData,
            'pagination' => $pagination,
            'nav' => $navigation,
            'rootPath' => $rootPath,
            'language' => $siteConfig->defaultLanguage,
            'metaTags' => MetaTagsBuilder::forPage($siteConfig, $collection->title, $collection->description, $permalink),
            'search' => $siteConfig->search !== null,
            'searchResults' => $siteConfig->search?->results ?? 10,
        ] + $uiViewData->toArray(), $rootPath);
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
