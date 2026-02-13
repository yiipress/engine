<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Build\ParallelEntryWriter;
use App\Content\Parser\ContentParser;
use App\Render\MarkdownRenderer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
final class BuildBench
{
    private ContentParser $parser;
    private MarkdownRenderer $renderer;
    private string $dataDir;
    private string $outputDir;

    public function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->renderer = new MarkdownRenderer();
        $this->dataDir = __DIR__ . '/data/content';
        $this->outputDir = __DIR__ . '/data/output';

        if (!is_dir($this->dataDir)) {
            throw new \RuntimeException(
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
    public function benchParseOnly(): void
    {
        $this->parser->parseSiteConfig($this->dataDir);
        $this->parser->parseNavigation($this->dataDir);
        $this->parser->parseCollections($this->dataDir);
        iterator_to_array($this->parser->parseAuthors($this->dataDir));

        foreach ($this->parser->parseAllEntries($this->dataDir) as $_) {
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

    private function runBuild(int $workerCount): void
    {
        $this->cleanOutputDir();

        $siteConfig = $this->parser->parseSiteConfig($this->dataDir);
        $collections = $this->parser->parseCollections($this->dataDir);

        $writer = new ParallelEntryWriter();
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
