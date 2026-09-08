<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\Author;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;

use function array_slice;

final readonly class FeedWorkerJob implements WorkerJobInterface
{
    /** @var list<array{collectionName: string, collection: Collection, entries: list<Entry>}> */
    private array $tasks;

    /**
     * @param list<array{collectionName: string, collection: Collection, entries: list<Entry>}> $tasks
     * @param array<string, Author> $authors
     */
    public function __construct(
        array $tasks,
        private SiteConfig $siteConfig,
        private string $outputDir,
        private string $contentDir,
        private array $authors,
        private bool $noWrite,
    ) {
        foreach ($tasks as &$task) {
            // Apply the feed limit before serializing entries for the worker.
            if ($task['collection']->feedLimit > 0) {
                $task['entries'] = array_slice($task['entries'], 0, $task['collection']->feedLimit);
            }
        }
        unset($task);

        $this->tasks = $tasks;
    }

    /** @return list<array{collectionName: string, collection: Collection, entries: list<Entry>}> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function siteConfig(): SiteConfig
    {
        return $this->siteConfig;
    }

    public function outputDir(): string
    {
        return $this->outputDir;
    }

    public function contentDir(): string
    {
        return $this->contentDir;
    }

    /** @return array<string, Author> */
    public function authors(): array
    {
        return $this->authors;
    }

    public function noWrite(): bool
    {
        return $this->noWrite;
    }
}
