<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Content\Parser\ContentParser;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

#[BeforeMethods('setUp')]
final class ContentParserBench
{
    private ContentParser $parser;
    private string $dataDir;

    public function setUp(): void
    {
        $this->parser = new ContentParser();
        $this->dataDir = __DIR__ . '/data/content';

        if (!is_dir($this->dataDir)) {
            throw new RuntimeException(
                'Benchmark data not found. Run: make bench-generate'
            );
        }
    }

    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseSiteConfig(): void
    {
        $this->parser->parseSiteConfig($this->dataDir);
    }

    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseNavigation(): void
    {
        $this->parser->parseNavigation($this->dataDir);
    }

    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseCollections(): void
    {
        $this->parser->parseCollections($this->dataDir);
    }

    #[Revs(5)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseAuthors(): void
    {
        foreach ($this->parser->parseAuthors($this->dataDir) as $_) {
            // do nothing
        }
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseAllEntries(): void
    {
        foreach ($this->parser->parseAllEntries($this->dataDir) as $_) {
            // do nothing
        }
    }

    #[Revs(1)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchParseAllEntriesWithBody(): void
    {
        foreach ($this->parser->parseAllEntries($this->dataDir) as $entry) {
            $entry->body();
        }
    }
}
