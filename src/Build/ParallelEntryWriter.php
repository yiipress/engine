<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Content\PermalinkResolver;
use App\Processor\ContentProcessorPipeline;
use RuntimeException;

use function pcntl_fork;
use function pcntl_waitpid;

final class ParallelEntryWriter
{
    public function __construct(
        private ContentProcessorPipeline $pipeline,
        private TemplateResolver $templateResolver,
        private ?BuildCache $cache = null,
    ) {}

    /**
     * @param array<string, Collection> $collections
     * @param list<string>|null $changedSourceFiles
     * @return array{written: int, tasks: list<array{entry: Entry, filePath: string}>, allEntries: list<Entry>}
     */
    public function write(
        ContentParser $parser,
        SiteConfig $siteConfig,
        array $collections,
        string $contentDir,
        string $outputDir,
        int $workerCount,
        bool $includeDrafts = false,
        bool $includeFuture = false,
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
        ?array $changedSourceFiles = null,
    ): array {
        $allEntries = $this->collectAllEntries($parser, $collections, $contentDir);
        $allTasks = $this->collectTasks($parser, $collections, $contentDir, $outputDir, $includeDrafts, $includeFuture);

        if ($changedSourceFiles !== null) {
            $changedSet = array_flip($changedSourceFiles);
            $tasks = array_values(array_filter(
                $allTasks,
                static fn (array $task) => isset($changedSet[$task['entry']->sourceFilePath()]),
            ));
        } else {
            $tasks = $allTasks;
        }

        if ($tasks !== []) {
            if ($workerCount <= 1) {
                $this->writeEntries($siteConfig, $tasks, $contentDir, $navigation, $crossRefResolver);
            } else {
                $this->writeParallel($siteConfig, $tasks, $contentDir, $workerCount, $navigation, $crossRefResolver);
            }
        }

        return ['written' => count($tasks), 'tasks' => $allTasks, 'allEntries' => $allEntries];
    }

    /**
     * @param array<string, Collection> $collections
     * @return list<Entry>
     */
    private function collectAllEntries(ContentParser $parser, array $collections, string $contentDir): array
    {
        $entries = [];
        foreach ($collections as $collectionName => $collection) {
            foreach ($parser->parseEntries($contentDir, $collectionName) as $entry) {
                if ($entry->title !== '') {
                    $entries[] = $entry;
                }
            }
        }
        return $entries;
    }

    /**
     * @param array<string, Collection> $collections
     * @return list<array{entry: Entry, filePath: string}>
     */
    private function collectTasks(
        ContentParser $parser,
        array $collections,
        string $contentDir,
        string $outputDir,
        bool $includeDrafts,
        bool $includeFuture,
    ): array {
        $now = new \DateTimeImmutable();
        $tasks = [];

        foreach ($collections as $collectionName => $collection) {
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

                $filePath = $outputDir . $permalink . 'index.html';
                $dirPath = dirname($filePath);

                if (!is_dir($dirPath)) {
                    mkdir($dirPath, 0o755, true);
                }

                $tasks[] = [
                    'entry' => $entry,
                    'filePath' => $filePath,
                ];
            }
        }

        return $tasks;
    }

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
     */
    private function writeEntries(SiteConfig $siteConfig, array $tasks, string $contentDir, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver): void
    {
        $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir);

        foreach ($tasks as $task) {
            file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $navigation, $crossRefResolver));
        }
    }

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, string $contentDir, int $workerCount, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver): int
    {
        $totalEntries = count($tasks);
        $pids = [];

        for ($workerIndex = 0; $workerIndex < $workerCount; $workerIndex++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Failed to fork worker process');
            }

            if ($pid === 0) {
                $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir);

                for ($i = $workerIndex; $i < $totalEntries; $i += $workerCount) {
                    $task = $tasks[$i];
                    file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $navigation, $crossRefResolver));
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        $failed = false;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if ($status !== 0) {
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException('One or more worker processes failed');
        }

        return $totalEntries;
    }

}
