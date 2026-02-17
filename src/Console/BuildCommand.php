<?php

declare(strict_types=1);

namespace App\Console;

use App\Build\AuthorPageWriter;
use App\Build\BuildCache;
use App\Build\CollectionListingWriter;
use App\Build\DateArchiveWriter;
use App\Build\EntryRenderer;
use App\Build\FeedGenerator;
use App\Build\ParallelEntryWriter;
use App\Build\SitemapGenerator;
use App\Build\TaxonomyPageWriter;
use App\Content\CrossReferenceResolver;
use App\Content\EntrySorter;
use App\Content\Model\Author;
use App\Content\Model\Collection;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Content\PermalinkResolver;
use App\Content\TaxonomyCollector;
use App\Processor\ContentProcessorPipeline;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Yii\Console\ExitCode;

use function str_starts_with;

#[AsCommand(
    name: 'build',
    description: 'Generates static HTML content from source files',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private Aliases $aliases,
        private ContentProcessorPipeline $contentPipeline,
        private ContentProcessorPipeline $feedPipeline,
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
        $rootPath = $this->aliases->get('@root');

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

        $output->writeln('<info>Parsing content...</info>');

        $parser = new ContentParser();
        $siteConfig = $parser->parseSiteConfig($contentDir);
        $navigation = $parser->parseNavigation($contentDir);
        $collections = $parser->parseCollections($contentDir);
        $authors = iterator_to_array($parser->parseAuthors($contentDir));

        $output->writeln("  Site: <comment>{$siteConfig->title}</comment>");
        $output->writeln('  Collections: <comment>' . count($collections) . '</comment>');
        $output->writeln('  Authors: <comment>' . count($authors) . '</comment>');
        $output->writeln('  Menus: <comment>' . count($navigation->menuNames()) . '</comment>');

        if ($dryRun) {
            return $this->dryRun($output, $parser, $siteConfig, $collections, $authors, $contentDir, $outputDir, $includeDrafts, $includeFuture, $navigation);
        }

        $output->writeln(
            $workerCount > 1
                ? "<info>Rendering and writing output with $workerCount workers...</info>"
                : '<info>Rendering and writing output...</info>',
        );

        $this->prepareOutputDir($outputDir);

        $fileToPermalink = [];
        foreach ($collections as $collectionName => $collection) {
            foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                if ($entry->title === '') {
                    $output->writeln('<error>  Skipping ' . $entry->sourceFilePath() . ': no title found</error>');
                    continue;
                }
                $relativePath = substr($entry->sourceFilePath(), strlen($contentDir) + 1);
                $fileToPermalink[$relativePath] = PermalinkResolver::resolve($entry, $collection);
            }
        }
        $standalonePages = [];
        foreach ($parser->parseStandalonePages($contentDir) as $page) {
            if ($page->title === '') {
                $output->writeln('<error>  Skipping ' . $page->sourceFilePath() . ': no title found</error>');
                continue;
            }
            $standalonePages[] = $page;
        }
        foreach ($standalonePages as $page) {
            $relativePath = substr($page->sourceFilePath(), strlen($contentDir) + 1);
            $fileToPermalink[$relativePath] = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
        }
        $crossRefResolver = new CrossReferenceResolver($fileToPermalink);

        $cache = null;
        if (!$noCache) {
            $cacheDir = $rootPath . '/runtime/cache/build';
            $cache = new BuildCache($cacheDir, EntryRenderer::ENTRY_TEMPLATE);
        }

        $writer = new ParallelEntryWriter($this->contentPipeline, $cache);
        $entryCount = $writer->write($parser, $siteConfig, $collections, $contentDir, $outputDir, $workerCount, $includeDrafts, $includeFuture, $navigation, $crossRefResolver);

        $output->writeln("  Entries written: <comment>$entryCount</comment>");

        if (!$includeDrafts) {
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => !$e->draft));
        }
        if (!$includeFuture) {
            $now = new \DateTimeImmutable();
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => $e->date === null || $e->date <= $now));
        }
        $renderer = new EntryRenderer($this->contentPipeline, $cache, $contentDir);
        foreach ($standalonePages as $page) {
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $filePath = $outputDir . $permalink . 'index.html';
            $dirPath = dirname($filePath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0o755, true);
            }
            file_put_contents($filePath, $renderer->render($siteConfig, $page, $navigation, $crossRefResolver));
        }
        if ($standalonePages !== []) {
            $output->writeln("  Standalone pages: <comment>" . count($standalonePages) . "</comment>");
        }

        $entriesByCollection = [];
        foreach ($collections as $collectionName => $collection) {
            $entries = array_values(array_filter(
                iterator_to_array($parser->parseEntries($contentDir, $collectionName)),
                static fn ($e) => $e->title !== '',
            ));
            if (!$includeDrafts) {
                $entries = array_values(array_filter($entries, static fn ($e) => !$e->draft));
            }
            if (!$includeFuture) {
                $now = new \DateTimeImmutable();
                $entries = array_values(array_filter($entries, static fn ($e) => $e->date === null || $e->date <= $now));
            }
            $entriesByCollection[$collectionName] = EntrySorter::sort($entries, $collection);
        }

        $feedGenerator = new FeedGenerator($this->feedPipeline);
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

        $listingWriter = new CollectionListingWriter();
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

        $archiveWriter = new DateArchiveWriter();
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
            $taxonomyWriter = new TaxonomyPageWriter();
            $taxonomyPageCount = $taxonomyWriter->write($siteConfig, $taxonomyData, $collections, $outputDir, $navigation);
            $output->writeln("  Taxonomy pages: <comment>$taxonomyPageCount</comment>");
        }

        if ($authors !== []) {
            $allEntries = isset($allEntries) ? $allEntries : array_merge(...array_values($entriesByCollection));
            $entriesByAuthor = [];
            foreach ($allEntries as $entry) {
                foreach ($entry->authors as $authorSlug) {
                    $entriesByAuthor[$authorSlug][] = $entry;
                }
            }
            $authorWriter = new AuthorPageWriter();
            $authorPageCount = $authorWriter->write($siteConfig, $authors, $entriesByAuthor, $collections, $outputDir, $navigation);
            $output->writeln("  Author pages: <comment>$authorPageCount</comment>");
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
        $now = new \DateTimeImmutable();
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

        $standalonePages = [];
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
            $standalonePages[] = $page;
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
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($outputDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
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
