<?php

declare(strict_types=1);

namespace App\Console;

use App\Build\AuthorPageWriter;
use App\Build\AssetFingerprintManifest;
use App\Build\BuildCache;
use App\Build\BuildManifest;
use App\Build\BuildDiagnostics;
use App\Build\CollectionListingWriter;
use App\Build\ContentAssetCopier;
use App\Build\DateArchiveWriter;
use App\Build\NotFoundPageWriter;
use App\Build\RedirectPageWriter;
use App\Build\RobotsTxtGenerator;
use App\Build\SearchIndexGenerator;
use App\Build\ThemeAssetCopier;
use App\Build\EntryRenderer;
use App\Build\FeedGenerator;
use App\Build\ParallelEntryWriter;
use App\Build\SitemapGenerator;
use App\Build\TemplateResolver;
use App\Build\TaxonomyPageWriter;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Content\CrossReferenceResolver;
use App\Content\EntrySorter;
use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Content\PermalinkResolver;
use App\Content\TaxonomyCollector;
use App\Environment;
use App\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use FilesystemIterator;
use FilesystemIterator as BaseFilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

use function count;
use function dirname;
use function array_keys;
use function array_filter;
use function array_unique;
use function array_values;
use function hash;
use function is_file;
use function pathinfo;
use function sort;
use function str_starts_with;
use function strtolower;
use function substr;
use function strlen;

#[AsCommand(
    name: 'build',
    description: 'Generates static HTML content from source files',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly string $rootPath,
        private readonly ContentProcessorPipeline $contentPipeline,
        private readonly ContentProcessorPipeline $feedPipeline,
        private readonly ThemeRegistry $themeRegistry,
        private readonly TemplateResolver $templateResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'content-dir',
            'c',
            InputOption::VALUE_REQUIRED,
            'Path to the content directory',
            'content',
        );
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_REQUIRED,
            'Path to the output directory',
            'output',
        );
        $this->addOption(
            'workers',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of parallel workers (1 = sequential)',
            '1',
        );
        $this->addOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Disable build cache',
        );
        $this->addOption(
            'drafts',
            null,
            InputOption::VALUE_NONE,
            'Include draft entries in the build',
        );
        $this->addOption(
            'future',
            null,
            InputOption::VALUE_NONE,
            'Include future-dated entries in the build',
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show what would be generated without writing files',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->rootPath;

        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $contentDir = $this->resolvePath($contentDirOption, $rootPath);

        /** @var string $outputDirOption */
        $outputDirOption = $input->getOption('output-dir');
        $outputDir = $this->resolvePath($outputDirOption, $rootPath);

        /** @var string $workersOption */
        $workersOption = $input->getOption('workers');
        $workerCount = max(1, (int) $workersOption);
        $noCache = (bool) $input->getOption('no-cache');
        $includeDrafts = $input->getOption('drafts') !== false ? (bool) $input->getOption('drafts') : Environment::isDev();
        $includeFuture = $input->getOption('future') !== false ? (bool) $input->getOption('future') : Environment::isDev();
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_dir($contentDir)) {
            $output->writeln("<error>Content directory not found: $contentDir</error>");
            return ExitCode::DATAERR;
        }

        $localTemplatesDir = $contentDir . '/templates';
        if (is_dir($localTemplatesDir)) {
            $this->themeRegistry->register(new Theme('local', $localTemplatesDir));
        }

        $contentAssetCopier = new ContentAssetCopier();
        $themeAssetCopier = new ThemeAssetCopier();
        $contentAssetMappings = $contentAssetCopier->mappings($contentDir);
        $themeAssetMappings = $themeAssetCopier->mappings($this->themeRegistry);
        $pipelineAssetMappings = $this->contentPipeline->collectAssetFiles();
        $allAssetMappings = $contentAssetMappings + $themeAssetMappings + $pipelineAssetMappings;
        $trackedDirectories = [];
        $manifest = null;
        $changedSourceFiles = null;
        $incremental = false;
        $configFiles = [];
        $allSourceFiles = [];

        if (!$dryRun && !$noCache) {
            $trackedDirectories = $this->collectTrackedDirectories($contentDir);
            foreach ($this->themeRegistry->all() as $theme) {
                $trackedDirectories += $this->collectTrackedDirectories($theme->path);
            }

            $manifestPath = $rootPath . '/runtime/cache/build-manifest-' . hash('xxh128', $outputDir) . '.json';
            $manifest = new BuildManifest($manifestPath);
            $manifest->load();
            $canUseManifestInventory = $manifest->sourceFiles() !== [] && $manifest->hasTrackedDirectories() && !$manifest->trackedDirectoriesChanged();

            if ($canUseManifestInventory) {
                $configFiles = $manifest->configFiles();
                $allSourceFiles = $manifest->sourceFiles();
            } else {
                $sourceInventory = $this->collectSourceInventory($contentDir, array_keys($allAssetMappings));
                $configFiles = $sourceInventory['configFiles'];
                $allSourceFiles = $sourceInventory['allSourceFiles'];
            }

            $configChanged = array_any($configFiles, fn($configFile) => $manifest->isChanged($configFile));

            if (!$configChanged) {
                $changedSourceFiles = array_values(array_unique([
                    ...$manifest->changedFiles($allSourceFiles),
                    ...$manifest->missingOutputFiles($allSourceFiles),
                ]));
            }

            $staleOutputs = $manifest->removedOutputs($allSourceFiles);

            foreach ($staleOutputs as $staleFile) {
                if (is_file($staleFile)) {
                    unlink($staleFile);
                }
            }

            if ($changedSourceFiles === [] && $staleOutputs === []) {
                $output->writeln('<info>No changes detected, nothing to build.</info>');
                return ExitCode::OK;
            }

            if ($changedSourceFiles !== null) {
                $incremental = true;
                $output->writeln(
                    $workerCount > 1
                        ? '<info>Incremental build with ' . $workerCount . ' workers (' . count($changedSourceFiles) . ' changed)...</info>'
                        : '<info>Incremental build (' . count($changedSourceFiles) . ' changed)...</info>',
                );
            } else {
                $output->writeln(
                    $workerCount > 1
                        ? "<info>Full rebuild with $workerCount workers (config changed)...</info>"
                        : '<info>Full rebuild (config changed)...</info>',
                );
            }

            if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
            }
        } elseif (!$dryRun) {
            $output->writeln(
                $workerCount > 1
                    ? "<info>Rendering and writing output with $workerCount workers...</info>"
                    : '<info>Rendering and writing output...</info>',
            );

            $this->prepareOutputDir($outputDir);
        }

        if ($manifest !== null && $allSourceFiles === []) {
            $sourceInventory = $this->collectSourceInventory($contentDir, array_keys($allAssetMappings));
            $configFiles = $sourceInventory['configFiles'];
            $allSourceFiles = $sourceInventory['allSourceFiles'];
        }

        $output->writeln('<info>Parsing content...</info>');

        $parser = new ContentParser();
        $siteConfig = $parser->parseSiteConfig($contentDir);
        $navigation = $parser->parseNavigation($contentDir);
        $collections = $parser->parseCollections($contentDir);
        $authors = iterator_to_array($parser->parseAuthors($contentDir));
        $parser->setAuthors($authors);

        $assetManifest = null;

        if ($siteConfig->assets->fingerprint) {
            $assetManifest = new AssetFingerprintManifest();
            foreach ($allAssetMappings as $sourcePath => $logicalPath) {
                $assetManifest->register($logicalPath, $sourcePath);
            }
        }

        $output->writeln("  Site: <comment>$siteConfig->title</comment>");
        $output->writeln('  Collections: <comment>' . count($collections) . '</comment>');
        $output->writeln('  Authors: <comment>' . count($authors) . '</comment>');
        $output->writeln('  Menus: <comment>' . count($navigation->menuNames()) . '</comment>');

        if ($dryRun) {
            return $this->dryRun($output, $parser, $siteConfig, $collections, $authors, $contentDir, $outputDir, $includeDrafts, $includeFuture);
        }

        /** @var array<string, list<Entry>> $rawEntriesByCollection */
        $rawEntriesByCollection = [];
        $fileToPermalink = [];

        foreach ($collections as $collectionName => $collection) {
            $collectionEntries = [];
            foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                if ($entry->title === '') {
                    $output->writeln('<error>  Skipping ' . $entry->sourceFilePath() . ': no title found</error>');
                    continue;
                }
                $collectionEntries[] = $entry;
                $relativePath = substr($entry->sourceFilePath(), strlen($contentDir) + 1);
                $fileToPermalink[$relativePath] = PermalinkResolver::resolve($entry, $collection);
            }
            $rawEntriesByCollection[$collectionName] = $collectionEntries;
        }

        $standalonePages = [];
        foreach ($parser->parseStandalonePages($contentDir) as $page) {
            if ($page->title === '') {
                $output->writeln('<error>  Skipping ' . $page->sourceFilePath() . ': no title found</error>');
                continue;
            }
            $standalonePages[] = $page;
            $relativePath = substr($page->sourceFilePath(), strlen($contentDir) + 1);
            $fileToPermalink[$relativePath] = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
        }

        $crossRefResolver = new CrossReferenceResolver($fileToPermalink);

        $changedSet = $changedSourceFiles !== null ? array_flip($changedSourceFiles) : null;
        $diagnostics = new BuildDiagnostics($contentDir, $fileToPermalink, $siteConfig, $authors);
        foreach ($rawEntriesByCollection as $entries) {
            foreach ($entries as $entry) {
                if ($changedSet !== null && !isset($changedSet[$entry->sourceFilePath()])) {
                    continue;
                }
                $diagnostics->check($entry);
            }
        }
        foreach ($standalonePages as $page) {
            if ($changedSet !== null && !isset($changedSet[$page->sourceFilePath()])) {
                continue;
            }
            $diagnostics->check($page);
        }
        foreach ($diagnostics->warnings() as $warning) {
            $output->writeln("<comment>  ⚠ $warning</comment>");
        }

        $now = new DateTimeImmutable();

        $allTasks = [];
        $redirectTasks = [];
        $entriesByCollection = [];
        foreach ($collections as $collectionName => $collection) {
            $filtered = [];
            foreach ($rawEntriesByCollection[$collectionName] as $entry) {
                if (!$includeDrafts && $entry->draft) {
                    continue;
                }
                if (!$includeFuture && $entry->date !== null && $entry->date > $now) {
                    continue;
                }

                $relativePath = substr($entry->sourceFilePath(), strlen($contentDir) + 1);
                $permalink = $fileToPermalink[$relativePath];
                $filePath = $outputDir . $permalink . 'index.html';

                if ($entry->redirectTo !== '') {
                    $redirectTasks[] = ['entry' => $entry, 'filePath' => $filePath];
                    continue;
                }

                $filtered[] = $entry;
                $allTasks[] = [
                    'entry' => $entry,
                    'filePath' => $filePath,
                    'permalink' => $permalink,
                ];
            }
            $entriesByCollection[$collectionName] = EntrySorter::sort($filtered, $collection);
        }

        $cache = null;
        if (!$noCache) {
            $cacheDir = $rootPath . '/runtime/cache/build';
            $cache = new BuildCache($cacheDir, $this->templateResolver->templateDirs());
        }

        if ($changedSet !== null) {
            $tasksToWrite = array_values(array_filter(
                $allTasks,
                static fn (array $task) => isset($changedSet[$task['entry']->sourceFilePath()]),
            ));
        } else {
            $tasksToWrite = $allTasks;
        }

        $writer = new ParallelEntryWriter($this->contentPipeline, $this->templateResolver, $cache, $assetManifest);
        $entriesWritten = $writer->write($siteConfig, $tasksToWrite, $contentDir, $workerCount, $navigation, $crossRefResolver, $authors);

        $output->writeln("  Entries written: <comment>$entriesWritten</comment>" . ($incremental ? ' (of ' . count($allTasks) . ' total)' : ''));

        if ($redirectTasks !== []) {
            $redirectWriter = new RedirectPageWriter();
            foreach ($redirectTasks as $task) {
                $redirectWriter->write($task['entry'], $task['filePath']);
            }
            $output->writeln('  Redirects written: <comment>' . count($redirectTasks) . '</comment>');
        }

        if ($manifest !== null) {
            foreach ($allTasks as $task) {
                $this->removeStaleOutputs($manifest->replace($task['entry']->sourceFilePath(), [$task['filePath']]));
            }
            foreach ($redirectTasks as $task) {
                $this->removeStaleOutputs($manifest->replace($task['entry']->sourceFilePath(), [$task['filePath']]));
            }
            foreach ($rawEntriesByCollection as $entries) {
                foreach ($entries as $entry) {
                    if (!isset($manifest->entries()[$entry->sourceFilePath()])) {
                        $this->removeStaleOutputs($manifest->replace($entry->sourceFilePath(), []));
                    }
                }
            }
        }

        if (!$includeDrafts) {
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => !$e->draft));
        }
        if (!$includeFuture) {
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => $e->date === null || $e->date <= $now));
        }
        $renderer = new EntryRenderer($this->contentPipeline, $this->templateResolver, $cache, $contentDir, $authors, $assetManifest);
        $redirectWriter ??= new RedirectPageWriter();
        $standalonePagesWritten = 0;
        foreach ($standalonePages as $page) {
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $filePath = $outputDir . $permalink . 'index.html';

            if ($changedSet !== null && !isset($changedSet[$page->sourceFilePath()])) {
                $manifest?->record($page->sourceFilePath(), [$filePath]);
                continue;
            }

            if ($page->redirectTo !== '') {
                $redirectWriter->write($page, $filePath);
            } else {
                $dirPath = dirname($filePath);
                if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
                }
                file_put_contents($filePath, $renderer->render($siteConfig, $page, $permalink, $navigation, $crossRefResolver));
            }
            $standalonePagesWritten++;

            if ($manifest !== null) {
                $this->removeStaleOutputs($manifest->replace($page->sourceFilePath(), [$filePath]));
            }
        }
        if ($standalonePages !== []) {
            $output->writeln("  Standalone pages: <comment>$standalonePagesWritten</comment>" . ($incremental ? ' (of ' . count($standalonePages) . ' total)' : ''));
        }

        $assetsCopied = $contentAssetCopier->copy($contentDir, $outputDir, $assetManifest);
        $assetsCopied += $themeAssetCopier->copy($this->themeRegistry, $outputDir, $assetManifest);

        foreach ($pipelineAssetMappings as $source => $target) {
            $resolvedTarget = $assetManifest?->resolve($target) ?? $target;
            $targetPath = $outputDir . '/' . $resolvedTarget;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDir));
            }
            copy($source, $targetPath);
            $assetsCopied++;
        }

        if ($manifest !== null) {
            foreach ($allAssetMappings as $sourcePath => $logicalPath) {
                $resolvedTarget = $assetManifest?->resolve($logicalPath) ?? $logicalPath;
                $this->removeStaleOutputs($manifest->replace($sourcePath, [$outputDir . '/' . $resolvedTarget]));
            }
        }

        if ($assetsCopied > 0) {
            $output->writeln("  Assets copied: <comment>$assetsCopied</comment>");
        }

        if ($assetManifest !== null && !$assetManifest->isEmpty()) {
            $output->writeln('  Asset fingerprints generated: <comment>' . count($assetManifest->all()) . '</comment>');
        }

        $feedGenerator = new FeedGenerator($this->feedPipeline, $authors);
        $feedCount = 0;
        foreach ($collections as $collectionName => $collection) {
            if (!$collection->feed) {
                continue;
            }

            $feedDir = $outputDir . '/' . $collectionName;
            if (!is_dir($feedDir) && !mkdir($feedDir, 0o755, true) && !is_dir($feedDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $feedDir));
            }

            file_put_contents(
                $feedDir . '/feed.xml',
                $feedGenerator->generateAtom($siteConfig, $collection, $entriesByCollection[$collectionName]),
            );
            file_put_contents(
                $feedDir . '/rss.xml',
                $feedGenerator->generateRss($siteConfig, $collection, $entriesByCollection[$collectionName]),
            );
            $feedCount++;
        }

        if ($feedCount > 0) {
            $output->writeln("  Feeds generated: <comment>$feedCount</comment> (Atom + RSS)");
        }

        $listingWriter = new CollectionListingWriter($this->templateResolver, $assetManifest);
        $listingPageCount = 0;
        foreach ($collections as $collectionName => $collection) {
            if (!$collection->listing) {
                continue;
            }
            $listingPageCount += $listingWriter->write(
                $siteConfig,
                $collection,
                $entriesByCollection[$collectionName] ?? [],
                $outputDir,
                $navigation,
            );
        }
        if ($listingPageCount > 0) {
            $output->writeln("  Listing pages: <comment>$listingPageCount</comment>");
        }

        $archiveWriter = new DateArchiveWriter($this->templateResolver, $assetManifest);
        $archivePageCount = 0;
        foreach ($collections as $collectionName => $collection) {
            if ($collection->sortBy !== 'date') {
                continue;
            }
            $archivePageCount += $archiveWriter->write(
                $siteConfig,
                $collection,
                $entriesByCollection[$collectionName] ?? [],
                $outputDir,
                $navigation,
            );
        }
        if ($archivePageCount > 0) {
            $output->writeln("  Archive pages: <comment>$archivePageCount</comment>");
        }

        $sitemapGenerator = new SitemapGenerator();
        $sitemapGenerator->generate($siteConfig, $collections, $entriesByCollection, $outputDir, $standalonePages, $authors);
        $output->writeln('  Sitemap generated.');

        $robotsGenerator = new RobotsTxtGenerator();
        $robots = $robotsGenerator->generate($siteConfig);
        if ($robots !== '') {
            file_put_contents($outputDir . '/robots.txt', $robots);
            $output->writeln('  robots.txt generated.');
        }

        $notFoundWriter = new NotFoundPageWriter($this->templateResolver, $assetManifest);
        $notFoundWriter->write($siteConfig, $outputDir, $navigation);
        $output->writeln('  404 page generated.');

        if ($siteConfig->search !== null) {
            $searchGenerator = new SearchIndexGenerator();
            $searchGenerator->generate($siteConfig, $collections, $entriesByCollection, $outputDir, $standalonePages);
            $output->writeln('  Search index generated.');
        }

        if ($siteConfig->taxonomies !== []) {
            $allEntries = array_merge(...array_values($entriesByCollection));
            $taxonomyData = TaxonomyCollector::collect($siteConfig->taxonomies, $allEntries);
            $taxonomyWriter = new TaxonomyPageWriter($this->templateResolver, $assetManifest);
            $taxonomyPageCount = $taxonomyWriter->write($siteConfig, $taxonomyData, $collections, $outputDir, $navigation);
            $output->writeln("  Taxonomy pages: <comment>$taxonomyPageCount</comment>");
        }

        if ($authors !== []) {
            $allEntries ??= array_merge(...array_values($entriesByCollection));
            $entriesByAuthor = [];
            foreach ($allEntries as $entry) {
                foreach ($entry->authors as $authorSlug) {
                    $entriesByAuthor[$authorSlug][] = $entry;
                }
            }
            $authorWriter = new AuthorPageWriter($this->templateResolver, $assetManifest);
            $authorPageCount = $authorWriter->write($siteConfig, $authors, $entriesByAuthor, $collections, $outputDir, $navigation);
            $output->writeln("  Author pages: <comment>$authorPageCount</comment>");
        }

        if ($manifest !== null) {
            $manifest->setConfigFiles($configFiles);
            $manifest->setTrackedDirectories($trackedDirectories);
            foreach ($configFiles as $configFile) {
                $this->removeStaleOutputs($manifest->replace($configFile, []));
            }
            $manifest->save();
        }

        $output->writeln('<info>Build complete.</info>');

        return ExitCode::OK;
    }

    /**
     * @param array<string, Collection> $collections
     * @param array<string, Author> $authors
     */
    private function dryRun(
        OutputInterface $output,
        ContentParser $parser,
        SiteConfig $siteConfig,
        array $collections,
        array $authors,
        string $contentDir,
        string $outputDir,
        bool $includeDrafts,
        bool $includeFuture,
    ): int {
        $output->writeln('<info>Dry run — files that would be generated:</info>');
        $now = new DateTimeImmutable();
        $files = [];

        foreach ($collections as $collectionName => $collection) {
            $entries = [];
            foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                if ($entry->title === '') {
                    continue;
                }
                if (!$includeDrafts && $entry->draft) {
                    continue;
                }
                if (!$includeFuture && $entry->date !== null && $entry->date > $now) {
                    continue;
                }
                $permalink = PermalinkResolver::resolve($entry, $collection);
                $files[] = $outputDir . $permalink . 'index.html';
                if ($entry->redirectTo === '') {
                    $entries[] = $entry;
                }
            }

            if ($collection->feed) {
                $files[] = $outputDir . '/' . $collectionName . '/feed.xml';
                $files[] = $outputDir . '/' . $collectionName . '/rss.xml';
            }

            if ($collection->listing) {
                $perPage = $collection->entriesPerPage;
                if ($perPage <= 0) {
                    $perPage = count($entries) ?: 1;
                }
                $totalPages = $entries !== [] ? (int) ceil(count($entries) / $perPage) : 1;
                $files[] = $outputDir . '/' . $collectionName . '/index.html';
                for ($p = 2; $p <= $totalPages; $p++) {
                    $files[] = $outputDir . '/' . $collectionName . '/page/' . $p . '/index.html';
                }
            }

            if ($collection->sortBy === 'date') {
                $byYear = [];
                $byMonth = [];
                foreach ($entries as $entry) {
                    if ($entry->date === null) {
                        continue;
                    }
                    $year = $entry->date->format('Y');
                    $month = $entry->date->format('m');
                    $byYear[$year] = true;
                    $byMonth[$year . '/' . $month] = true;
                }
                foreach (array_keys($byYear) as $year) {
                    $files[] = $outputDir . '/' . $collectionName . '/' . $year . '/index.html';
                }
                foreach (array_keys($byMonth) as $yearMonth) {
                    $files[] = $outputDir . '/' . $collectionName . '/' . $yearMonth . '/index.html';
                }
            }
        }

        foreach ($parser->parseStandalonePages($contentDir) as $page) {
            if ($page->title === '') {
                continue;
            }
            if (!$includeDrafts && $page->draft) {
                continue;
            }
            if (!$includeFuture && $page->date !== null && $page->date > $now) {
                continue;
            }
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $files[] = $outputDir . $permalink . 'index.html';
        }

        $contentAssetCopier = new ContentAssetCopier();
        $themeAssetCopier = new ThemeAssetCopier();
        $assetMappings = $contentAssetCopier->mappings($contentDir)
            + $themeAssetCopier->mappings($this->themeRegistry)
            + $this->contentPipeline->collectAssetFiles();

        $assetManifest = null;
        if ($siteConfig->assets->fingerprint) {
            $assetManifest = new AssetFingerprintManifest();
            foreach ($assetMappings as $sourcePath => $logicalPath) {
                $assetManifest->register($logicalPath, $sourcePath);
            }
        }

        foreach ($assetMappings as $logicalPath) {
            $files[] = $outputDir . '/' . ($assetManifest?->resolve($logicalPath) ?? $logicalPath);
        }

        $files[] = $outputDir . '/sitemap.xml';

        if ($siteConfig->robotsTxt->generate) {
            $files[] = $outputDir . '/robots.txt';
        }

        $files[] = $outputDir . '/404.html';

        if ($siteConfig->search !== null) {
            $files[] = $outputDir . '/search-index.json';
        }

        if ($siteConfig->taxonomies !== []) {
            $allEntries = [];
            foreach ($collections as $collectionName => $collection) {
                foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                    if ($entry->title === '' || (!$includeDrafts && $entry->draft)) {
                        continue;
                    }
                    if (!$includeFuture && $entry->date !== null && $entry->date > $now) {
                        continue;
                    }
                    $allEntries[] = $entry;
                }
            }
            $taxonomyData = TaxonomyCollector::collect($siteConfig->taxonomies, $allEntries);
            foreach ($taxonomyData as $taxonomyName => $terms) {
                if ($terms === []) {
                    continue;
                }
                $files[] = $outputDir . '/' . $taxonomyName . '/index.html';
                foreach (array_keys($terms) as $term) {
                    $files[] = $outputDir . '/' . $taxonomyName . '/' . $term . '/index.html';
                }
            }
        }

        if ($authors !== []) {
            $files[] = $outputDir . '/authors/index.html';
            foreach (array_keys($authors) as $slug) {
                $files[] = $outputDir . '/authors/' . $slug . '/index.html';
            }
        }

        sort($files);
        foreach ($files as $file) {
            $output->writeln('  ' . $file);
        }

        $output->writeln('');
        $output->writeln('<info>Total: ' . count($files) . ' files</info>');

        return ExitCode::OK;
    }

    private function prepareOutputDir(string $outputDir): void
    {
        if (is_dir($outputDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($outputDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if ($item->isDir()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }
        } elseif (!mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
        }
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }

    /**
     * @param list<string> $outputFiles
     */
    private function removeStaleOutputs(array $outputFiles): void
    {
        foreach ($outputFiles as $outputFile) {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    /**
     * @param list<string> $assetSourceFiles
     * @return array{allSourceFiles: list<string>, configFiles: list<string>}
     */
    private function collectSourceInventory(string $contentDir, array $assetSourceFiles): array
    {
        $configFiles = array_filter([
            $contentDir . '/config.yaml',
            $contentDir . '/navigation.yaml',
        ], is_file(...));

        $contentFiles = [];
        $iterator = new FilesystemIterator($contentDir, BaseFilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                $name = $item->getFilename();
                if ($name === 'assets' || $name === 'authors' || $name === 'templates') {
                    continue;
                }

                $collectionConfig = $item->getPathname() . '/_collection.yaml';
                if (is_file($collectionConfig)) {
                    $configFiles[] = $collectionConfig;

                    $collectionIterator = new FilesystemIterator($item->getPathname(), BaseFilesystemIterator::SKIP_DOTS);
                    foreach ($collectionIterator as $collectionItem) {
                        /** @var SplFileInfo $collectionItem */
                        if ($collectionItem->isDir() || strtolower($collectionItem->getExtension()) !== 'md') {
                            continue;
                        }
                        $contentFiles[] = $collectionItem->getPathname();
                    }
                }

                continue;
            }

            if (strtolower($item->getExtension()) === 'md') {
                $contentFiles[] = $item->getPathname();
            }
        }

        $templateFiles = $this->collectTemplateFiles();
        $configFiles = array_values(array_unique([...$configFiles, ...$templateFiles]));
        sort($configFiles);
        sort($contentFiles);
        sort($assetSourceFiles);

        return [
            'allSourceFiles' => [...$contentFiles, ...$assetSourceFiles, ...$configFiles],
            'configFiles' => $configFiles,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectTemplateFiles(): array
    {
        $files = [];

        foreach ($this->templateResolver->templateDirs() as $templateDir) {
            if (!is_dir($templateDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if (!$item->isFile() || strtolower((string) pathinfo($item->getFilename(), PATHINFO_EXTENSION)) !== 'php') {
                    continue;
                }
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, int>
     */
    private function collectTrackedDirectories(string $rootDir): array
    {
        if (!is_dir($rootDir)) {
            return [];
        }

        $directories = [$rootDir => (int) filemtime($rootDir)];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (!$item->isDir()) {
                continue;
            }
            $directories[$item->getPathname()] = (int) $item->getMTime();
        }

        return $directories;
    }
}
