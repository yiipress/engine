<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Build\BuildCache;
use App\Build\ParallelEntryWriter;
use App\Build\TemplateResolver;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Content\Parser\ContentParser;
use App\Processor\ContentProcessorPipeline;
use App\Processor\MarkdownProcessor;
use App\Render\MarkdownRenderer;
use FilesystemIterator;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

#[BeforeMethods('setUp')]
final class BuildBench
{
    private ContentParser $parser;
    private MarkdownRenderer $renderer;
    private ContentProcessorPipeline $pipeline;
    private TemplateResolver $templateResolver;
    private string $dataDir;
    private string $outputDir;
    private string $cacheDir;

    public function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->renderer = new MarkdownRenderer();
        $this->pipeline = new ContentProcessorPipeline(new MarkdownProcessor());

        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__) . '/themes/minimal'));
        $this->templateResolver = new TemplateResolver($registry);

        $this->dataDir = __DIR__ . '/data/content';
        $this->outputDir = __DIR__ . '/data/output';
        $this->cacheDir = __DIR__ . '/data/cache';

        if (!is_dir($this->dataDir)) {
            throw new RuntimeException(
                'Benchmark data not found. Run: make bench-generate'
            );
        }

        $this->cleanOutputDir();
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuildSequential(): void
    {
        $this->runBuild(1);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuild2Workers(): void
    {
        $this->runBuild(2);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuild4Workers(): void
    {
        $this->runBuild(4);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuild8Workers(): void
    {
        $this->runBuild(8);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuildCachedSequential(): void
    {
        $this->runBuild(1, true);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchFullBuildCached4Workers(): void
    {
        $this->runBuild(4, true);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseOnly(): void
    {
        $this->parser->parseSiteConfig($this->dataDir);
        $this->parser->parseNavigation($this->dataDir);
        $this->parser->parseCollections($this->dataDir);
        iterator_to_array($this->parser->parseAuthors($this->dataDir));

        foreach ($this->parser->parseAllEntries($this->dataDir) as $_) {
            // do nothing
        }
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRenderOnly(): void
    {
        $collections = $this->parser->parseCollections($this->dataDir);

        foreach ($collections as $collectionName => $_) {
            foreach ($this->parser->parseEntries($this->dataDir, $collectionName) as $entry) {
                $this->renderer->render($entry->body());
            }
        }
    }

    private function runBuild(int $workerCount, bool $useCache = false): void
    {
        $this->cleanOutputDir();

        $cache = null;
        if ($useCache) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0o755, true);
            }
            $cache = new BuildCache($this->cacheDir, $this->templateResolver->templateDirs());
        }

        $siteConfig = $this->parser->parseSiteConfig($this->dataDir);
        $collections = $this->parser->parseCollections($this->dataDir);

        $writer = new ParallelEntryWriter($this->pipeline, $this->templateResolver, $cache);
        $writer->write($this->parser, $siteConfig, $collections, $this->dataDir, $this->outputDir, $workerCount);
    }

    private function cleanOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0o755, true);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputDir, FilesystemIterator::SKIP_DOTS),
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
    }
}
