<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use RuntimeException;

use function array_slice;
use function ceil;
use function count;
use function dirname;
use function min;
use function pcntl_fork;
use function pcntl_wexitstatus;
use function pcntl_waitpid;

final readonly class ParallelEntryWriter
{
    private const int MIN_TASKS_PER_WORKER = 64;

    public function __construct(
        private ContentProcessorPipeline $pipeline,
        private TemplateResolver $templateResolver,
        private ?BuildCache $cache = null,
        private ?AssetFingerprintManifest $assetManifest = null,
    ) {}

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string}> $tasks
     * @return int number of entries written
     */
    public function write(
        SiteConfig $siteConfig,
        array $tasks,
        string $contentDir,
        int $workerCount,
        ?Navigation $navigation = null,
        ?CrossReferenceResolver $crossRefResolver = null,
        array $authors = [],
    ): int {
        if ($tasks === []) {
            return 0;
        }

        $effectiveWorkerCount = $this->workerCountFor(count($tasks), $workerCount);

        $dirs = [];
        foreach ($tasks as $task) {
            $dirs[dirname($task['filePath'])] = true;
        }
        foreach ($dirs as $dirPath => $_) {
            if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
            }
        }

        if ($effectiveWorkerCount <= 1) {
            $this->writeEntries($siteConfig, $tasks, $contentDir, $navigation, $crossRefResolver, $authors);
        } else {
            $this->writeParallel($siteConfig, $tasks, $contentDir, $effectiveWorkerCount, $navigation, $crossRefResolver, $authors);
        }

        return count($tasks);
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string}> $tasks
     */
    private function writeEntries(SiteConfig $siteConfig, array $tasks, string $contentDir, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors): void
    {
        $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors, $this->assetManifest);

        foreach ($tasks as $task) {
            file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $task['permalink'], $navigation, $crossRefResolver));
        }
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string}> $tasks
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, string $contentDir, int $workerCount, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors): void
    {
        $taskChunks = $this->partitionTasks($tasks, $workerCount);
        $pids = [];

        foreach ($taskChunks as $chunk) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Failed to fork worker process');
            }

            if ($pid === 0) {
                $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors, $this->assetManifest);

                foreach ($chunk as $task) {
                    file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $task['permalink'], $navigation, $crossRefResolver));
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        $failed = false;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) !== 0) {
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException('One or more worker processes failed');
        }
    }

    public function workerCountFor(int $taskCount, int $requestedWorkerCount): int
    {
        if ($requestedWorkerCount <= 1 || $taskCount < self::MIN_TASKS_PER_WORKER * 2) {
            return 1;
        }

        return min($requestedWorkerCount, $taskCount);
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string}> $tasks
     * @return list<list<array{entry: Entry, filePath: string, permalink: string}>>
     */
    private function partitionTasks(array $tasks, int $workerCount): array
    {
        $chunkSize = (int) ceil(count($tasks) / $workerCount);
        $chunks = [];

        for ($offset = 0, $taskCount = count($tasks); $offset < $taskCount; $offset += $chunkSize) {
            $chunks[] = array_slice($tasks, $offset, $chunkSize);
        }

        return $chunks;
    }
}
