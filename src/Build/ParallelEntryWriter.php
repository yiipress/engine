<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Collection;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Content\Parser\ContentParser;
use App\Content\PermalinkResolver;
use RuntimeException;

use function pcntl_fork;
use function pcntl_waitpid;

final class ParallelEntryWriter
{
    public function __construct(
        private ?BuildCache $cache = null,
    ) {}

    /**
     * @param array<string, Collection> $collections
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
    ): int {
        $tasks = $this->collectTasks($parser, $collections, $contentDir, $outputDir, $includeDrafts, $includeFuture);

        if ($tasks === []) {
            return 0;
        }

        if ($workerCount <= 1) {
            $this->writeEntries($siteConfig, $tasks, $navigation);
            return count($tasks);
        }

        return $this->writeParallel($siteConfig, $tasks, $workerCount, $navigation);
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
    private function writeEntries(SiteConfig $siteConfig, array $tasks, ?Navigation $navigation): void
    {
        $renderer = new EntryRenderer($this->cache);

        foreach ($tasks as $task) {
            file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $navigation));
        }
    }

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, int $workerCount, ?Navigation $navigation): int
    {
        $totalEntries = count($tasks);
        $pids = [];

        for ($workerIndex = 0; $workerIndex < $workerCount; $workerIndex++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Failed to fork worker process');
            }

            if ($pid === 0) {
                $renderer = new EntryRenderer($this->cache);

                for ($i = $workerIndex; $i < $totalEntries; $i += $workerCount) {
                    $task = $tasks[$i];
                    file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $navigation));
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
