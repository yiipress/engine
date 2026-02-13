<?php

declare(strict_types=1);

namespace App\Console;

use App\Build\BuildCache;
use App\Build\CollectionListingWriter;
use App\Build\EntryRenderer;
use App\Build\FeedGenerator;
use App\Build\ParallelEntryWriter;
use App\Build\SitemapGenerator;
use App\Build\TaxonomyPageWriter;
use App\Content\EntrySorter;
use App\Content\Parser\ContentParser;
use App\Content\TaxonomyCollector;
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

        $output->writeln(
            $workerCount > 1
                ? "<info>Rendering and writing output with $workerCount workers...</info>"
                : '<info>Rendering and writing output...</info>',
        );

        $this->prepareOutputDir($outputDir);

        $cache = null;
        if (!$noCache) {
            $cacheDir = $rootPath . '/runtime/cache/build';
            $cache = new BuildCache($cacheDir, EntryRenderer::ENTRY_TEMPLATE);
        }

        $writer = new ParallelEntryWriter($cache);
        $entryCount = $writer->write($parser, $siteConfig, $collections, $contentDir, $outputDir, $workerCount, $includeDrafts, $includeFuture, $navigation);

        $output->writeln("  Entries written: <comment>$entryCount</comment>");

        $renderer = new EntryRenderer($cache);
        $standalonePages = iterator_to_array($parser->parseStandalonePages($contentDir));
        if (!$includeDrafts) {
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => !$e->draft));
        }
        if (!$includeFuture) {
            $now = new \DateTimeImmutable();
            $standalonePages = array_values(array_filter($standalonePages, static fn ($e) => $e->date === null || $e->date <= $now));
        }
        foreach ($standalonePages as $page) {
            $permalink = $page->permalink !== '' ? $page->permalink : '/' . $page->slug . '/';
            $filePath = $outputDir . $permalink . 'index.html';
            $dirPath = dirname($filePath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0o755, true);
            }
            file_put_contents($filePath, $renderer->render($siteConfig, $page, $navigation));
        }
        if ($standalonePages !== []) {
            $output->writeln("  Standalone pages: <comment>" . count($standalonePages) . "</comment>");
        }

        $entriesByCollection = [];
        foreach ($collections as $collectionName => $collection) {
            $entries = iterator_to_array($parser->parseEntries($contentDir, $collectionName));
            if (!$includeDrafts) {
                $entries = array_values(array_filter($entries, static fn ($e) => !$e->draft));
            }
            if (!$includeFuture) {
                $now = new \DateTimeImmutable();
                $entries = array_values(array_filter($entries, static fn ($e) => $e->date === null || $e->date <= $now));
            }
            $entriesByCollection[$collectionName] = EntrySorter::sort($entries, $collection);
        }

        $feedGenerator = new FeedGenerator();
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

        $sitemapGenerator = new SitemapGenerator();
        $sitemapGenerator->generate($siteConfig, $collections, $entriesByCollection, $outputDir, $standalonePages);
        $output->writeln('  Sitemap generated.');

        if ($siteConfig->taxonomies !== []) {
            $allEntries = array_merge(...array_values($entriesByCollection));
            $taxonomyData = TaxonomyCollector::collect($siteConfig->taxonomies, $allEntries);
            $taxonomyWriter = new TaxonomyPageWriter();
            $taxonomyPageCount = $taxonomyWriter->write($siteConfig, $taxonomyData, $collections, $outputDir, $navigation);
            $output->writeln("  Taxonomy pages: <comment>$taxonomyPageCount</comment>");
        }

        $output->writeln('<info>Build complete.</info>');

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
