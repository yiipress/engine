<?php

declare(strict_types=1);

namespace App\Console;

use App\Build\AuthorPageWriter;
use App\Build\BuildCache;
use App\Build\BuildManifest;
use App\Build\BuildDiagnostics;
use App\Build\CollectionListingWriter;
use App\Build\ContentAssetCopier;
use App\Build\DateArchiveWriter;
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
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Content\PermalinkResolver;
use App\Content\TaxonomyCollector;
use App\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

use function count;
use function dirname;
use function str_starts_with;
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
        $includeDrafts = (bool) $input->getOption('drafts');
        $includeFuture = (bool) $input->getOption('future');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_dir($contentDir)) {
            $output->writeln("<error>Content directory not found: $contentDir</error>");
            return ExitCode::DATAERR;
        }

        $localTemplatesDir = $contentDir . '/templates';
        if (is_dir($localTemplatesDir)) {
            $this->themeRegistry->register(new Theme('local', $localTemplatesDir));
        }

        $output->writeln('<info>Parsing content...</info>');

        $parser = new ContentParser();
        $siteConfig = $parser->parseSiteConfig($contentDir);
        $navigation = $parser->parseNavigation($contentDir);
        $collections = $parser->parseCollections($contentDir);
        $authors = iterator_to_array($parser->parseAuthors($contentDir));
        $parser->setAuthors($authors);

        $output->writeln("  Site: <comment>$siteConfig->title</comment>");
        $output->writeln('  Collections: <comment>' . count($collections) . '</comment>');
        $output->writeln('  Authors: <comment>' . count($authors) . '</comment>');
        $output->writeln('  Menus: <comment>' . count($navigation->menuNames()) . '</comment>');

        if ($dryRun) {
            return $this->dryRun($output, $parser, $siteConfig, $collections, $authors, $contentDir, $outputDir, $includeDrafts, $includeFuture, $navigation);
        }

        /** @var array<string, list<Entry>> $rawEntriesByCollection */
        $rawEntriesByCollection = [];
        $fileToPermalink = [];
        $allSourceFiles = [];

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
                $allSourceFiles[] = $entry->sourceFilePath();
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
            $allSourceFiles[] = $page->sourceFilePath();
        }

        $crossRefResolver = new CrossReferenceResolver($fileToPermalink);

        $diagnostics = new BuildDiagnostics($contentDir, $fileToPermalink, $siteConfig, $authors);
        foreach ($rawEntriesByCollection as $entries) {
            foreach ($entries as $entry) {
                $diagnostics->check($entry);
            }
        }
        foreach ($standalonePages as $page) {
            $diagnostics->check($page);
        }
        foreach ($diagnostics->warnings() as $warning) {
            $output->writeln("<comment>  ⚠ $warning</comment>");
        }

        $manifest = null;
        $changedSourceFiles = null;
        $incremental = false;

        $configFiles = array_filter([
            $contentDir . '/config.yaml',
            $contentDir . '/navigation.yaml',
        ], 'is_file');
        foreach ($collections as $collectionName => $collection) {
            $collectionConfig = $contentDir . '/' . $collectionName . '/_collection.yaml';
            if (is_file($collectionConfig)) {
                $configFiles[] = $collectionConfig;
            }
        }

        if (!$noCache) {
            $manifestPath = $rootPath . '/runtime/cache/build-manifest-' . hash('xxh128', $outputDir) . '.json';
            $manifest = new BuildManifest($manifestPath);
            $manifest->load();

            $configChanged = false;
            foreach ($configFiles as $configFile) {
                if ($manifest->isChanged($configFile)) {
                    $configChanged = true;
                    break;
                }
            }

            if ($configChanged) {
                $changedSourceFiles = null;
            } else {
                $changedSourceFiles = $manifest->changedFiles($allSourceFiles);
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

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0o755, true);
            }
        } else {
            $output->writeln(
                $workerCount > 1
                    ? "<info>Rendering and writing output with $workerCount workers...</info>"
                    : '<info>Rendering and writing output...</info>',
            );

            $this->prepareOutputDir($outputDir);
        }

        $now = new DateTimeImmutable();

        $allTasks = [];
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
                $filtered[] = $entry;

                $relativePath = substr($entry->sourceFilePath(), strlen($contentDir) + 1);
                $permalink = $fileToPermalink[$relativePath];
                $allTasks[] = [
                    'entry' => $entry,
                    'filePath' => $outputDir . $permalink . 'index.html',
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

        $changedSet = $changedSourceFiles !== null ? array_flip($changedSourceFiles) : null;

        if ($changedSet !== null) {
            $tasksToWrite = array_values(array_filter(
                $allTasks,
                static fn (array $task) => isset($changedSet[$task['entry']->sourceFilePath()]),
            ));
        } else {
            $tasksToWrite = $allTasks;
        }

        $writer = new ParallelEntryWriter($this->contentPipeline, $this->templateResolver, $cache);
        $entriesWritten = $writer->write($siteConfig, $tasksToWrite, $contentDir, $workerCount, $navigation, $crossRefResolver, $authors);

        $output->writeln("  Entries written: <comment>$entriesWritten</comment>" . ($incremental ? ' (of ' . count($allTasks) . ' total)' : ''));

        if ($manifest !== null) {
            foreach ($allTasks as $task) {
                $manifest->record($task['entry']->sourceFilePath(), [$task['filePath']]);
            }
            foreach ($rawEntriesByCollection as $entries) {
                foreach ($entries as $entry) {
                    if (!isset($manifest->entries()[$entry->sourceFilePath()])) {
                        $manifest->record($entry->sourceFilePath(), []);
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
        $renderer = new EntryRenderer($this->contentPipeline, $this->templateResolver, $cache, $contentDir, $authors);
        $standalonePagesWritten = 0;
        foreach ($standalonePages as $page) {
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $filePath = $outputDir . $permalink . 'index.html';

            if ($changedSet !== null && !isset($changedSet[$page->sourceFilePath()])) {
                $manifest?->record($page->sourceFilePath(), [$filePath]);
                continue;
            }

            $dirPath = dirname($filePath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0o755, true);
            }
            file_put_contents($filePath, $renderer->render($siteConfig, $page, $permalink, $navigation, $crossRefResolver));
            $standalonePagesWritten++;

            $manifest?->record($page->sourceFilePath(), [$filePath]);
        }
        if ($standalonePages !== []) {
            $output->writeln("  Standalone pages: <comment>$standalonePagesWritten</comment>" . ($incremental ? ' (of ' . count($standalonePages) . ' total)' : ''));
        }

        $assetCopier = new ContentAssetCopier();
        $assetsCopied = $assetCopier->copy($contentDir, $outputDir);

        $themeAssetCopier = new ThemeAssetCopier();
        $assetsCopied += $themeAssetCopier->copy($this->themeRegistry, $outputDir);

        if ($assetsCopied > 0) {
            $output->writeln("  Assets copied: <comment>$assetsCopied</comment>");
        }

        $feedGenerator = new FeedGenerator($this->feedPipeline, $authors);
        $feedCount = 0;
        foreach ($collections as $collectionName => $collection) {
            if (!$collection->feed) {
                continue;
            }

            $feedDir = $outputDir . '/' . $collectionName;
            if (!is_dir($feedDir)) {
                mkdir($feedDir, 0o755, true);
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

        $listingWriter = new CollectionListingWriter($this->templateResolver);
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

        $archiveWriter = new DateArchiveWriter($this->templateResolver);
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

        if ($siteConfig->taxonomies !== []) {
            $allEntries = array_merge(...array_values($entriesByCollection));
            $taxonomyData = TaxonomyCollector::collect($siteConfig->taxonomies, $allEntries);
            $taxonomyWriter = new TaxonomyPageWriter($this->templateResolver);
            $taxonomyPageCount = $taxonomyWriter->write($siteConfig, $taxonomyData, $collections, $outputDir, $navigation);
            $output->writeln("  Taxonomy pages: <comment>$taxonomyPageCount</comment>");
        }

        if ($authors !== []) {
            $allEntries = $allEntries ?? array_merge(...array_values($entriesByCollection));
            $entriesByAuthor = [];
            foreach ($allEntries as $entry) {
                foreach ($entry->authors as $authorSlug) {
                    $entriesByAuthor[$authorSlug][] = $entry;
                }
            }
            $authorWriter = new AuthorPageWriter($this->templateResolver);
            $authorPageCount = $authorWriter->write($siteConfig, $authors, $entriesByAuthor, $collections, $outputDir, $navigation);
            $output->writeln("  Author pages: <comment>$authorPageCount</comment>");
        }

        if ($manifest !== null) {
            foreach ($configFiles as $configFile) {
                $manifest->record($configFile, []);
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
        ?Navigation $navigation,
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
                $entries[] = $entry;
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

        $files[] = $outputDir . '/sitemap.xml';

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
        } else {
            mkdir($outputDir, 0o755, true);
        }
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}
