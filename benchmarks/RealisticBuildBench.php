<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Build\BuildCache;
use App\Build\EntryRenderer;
use App\Build\ParallelEntryWriter;
use App\Content\Parser\ContentParser;
use App\Render\MarkdownRenderer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
final class RealisticBuildBench
{
    private ContentParser $parser;
    private MarkdownRenderer $renderer;
    private string $dataDir;
    private string $outputDir;
    private string $cacheDir;

    public function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->renderer = new MarkdownRenderer();
        $this->dataDir = __DIR__ . '/data/realistic-content';
        $this->outputDir = __DIR__ . '/data/realistic-output';
        $this->cacheDir = __DIR__ . '/data/realistic-cache';

        if (!is_dir($this->dataDir)) {
            throw new \RuntimeException(
                'Realistic benchmark data not found. Run: make bench-generate-realistic'
            );
        }

        $this->cleanOutputDir();
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealisticSequential(): void
    {
        $this->runBuild(1, false);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealistic4Workers(): void
    {
        $this->runBuild(4, false);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealistic8Workers(): void
    {
        $this->runBuild(8, false);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealisticCachedSequential(): void
    {
        $this->runBuild(1, true);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealisticCached4Workers(): void
    {
        $this->runBuild(4, true);
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRealisticRenderOnly(): void
    {
        $collections = $this->parser->parseCollections($this->dataDir);

        foreach ($collections as $collectionName => $_) {
            foreach ($this->parser->parseEntries($this->dataDir, $collectionName) as $entry) {
                $this->renderer->render($entry->body());
            }
        }
    }

    private function runBuild(int $workerCount, bool $useCache): void
    {
        $this->cleanOutputDir();

        $cache = null;
        if ($useCache) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0o755, true);
            }
            $cache = new BuildCache($this->cacheDir, EntryRenderer::ENTRY_TEMPLATE);
        }

        $siteConfig = $this->parser->parseSiteConfig($this->dataDir);
        $collections = $this->parser->parseCollections($this->dataDir);

        $writer = new ParallelEntryWriter($cache);
        $writer->write($this->parser, $siteConfig, $collections, $this->dataDir, $this->outputDir, $workerCount);
    }

    private function cleanOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0o755, true);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->outputDir, \FilesystemIterator::SKIP_DOTS),
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
    }
}
