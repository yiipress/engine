<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\FeedWorkerJob;
use YiiPress\Build\FeedWriter;
use YiiPress\Build\ParallelTaskRunner;
use YiiPress\Build\WorkerJobInterface;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\Parser\ContentParser;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\Question\QuestionProcessor;
use YiiPress\Processor\TagLinkProcessor;
use YiiPress\Render\MarkdownRenderer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Revs(1)]
#[Iterations(5)]
#[Warmup(1)]
final class FeedBatchBench
{
    /** @var array<int, list<array{collectionName: string, collection: Collection, entries: list<Entry>}>> */
    private array $tasks;
    private SiteConfig $config;
    private FeedWriter $writer;
    private string $contentDir;

    public function setUp(): void
    {
        $this->contentDir = __DIR__ . '/data/realistic-content';
        $parser = new ContentParser();
        $this->config = $parser->parseSiteConfig($this->contentDir);
        $question = new QuestionProcessor();
        $pipeline = new ContentProcessorPipeline($question, new MarkdownProcessor(new MarkdownRenderer($this->config->markdown)), new TagLinkProcessor(), $question);
        $pipeline->applySiteConfig($this->config);
        $this->writer = new FeedWriter($pipeline, []);
        $this->tasks = [20 => [], 100 => [], 0 => []];
        foreach ($parser->parseCollections($this->contentDir) as $collection) {
            $entries = iterator_to_array($parser->parseEntries($this->contentDir, $collection->name), false);
            foreach ([20, 100, 0] as $limit) {
                $limited = new Collection(
                    $collection->name, $collection->title, $collection->description, $collection->permalink,
                    $collection->sortBy, $collection->sortOrder, $collection->entriesPerPage,
                    true, $collection->listing, feedLimit: $limit,
                );
                $this->tasks[$limit][] = ['collectionName' => $collection->name, 'collection' => $limited, 'entries' => $entries];
            }
        }
    }

    public function provideLimits(): iterable
    {
        yield '20 per collection' => ['limit' => 20];
        yield '100 per collection' => ['limit' => 100];
        yield 'unlimited' => ['limit' => 0];
    }

    #[ParamProviders('provideLimits')]
    public function benchSequential(array $params): void
    {
        $this->run($params['limit'], 1);
    }

    #[ParamProviders('provideLimits')]
    public function benchParallel(array $params): void
    {
        $this->run($params['limit'], 4);
    }

    #[ParamProviders('provideLimits')]
    public function benchSelectedWorkers(array $params): void
    {
        $limit = $params['limit'];
        $this->run($limit, $this->writer->workerCountFor($this->tasks[$limit], 4));
    }

    private function run(int $limit, int $workers): void
    {
        new ParallelTaskRunner()->run(
            $this->tasks[$limit], $workers,
            fn(array $task): int => $this->writer->writeTask($task, $this->config, '/unused', true),
            fn(array $chunk): WorkerJobInterface => new FeedWorkerJob($chunk, $this->config, '/unused', $this->contentDir, [], true),
            minTasksPerWorker: 1,
        );
    }
}
