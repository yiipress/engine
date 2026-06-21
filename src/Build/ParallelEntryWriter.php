<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\CrossReferenceResolver;
use YiiPress\Content\I18n\TranslationIndex;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\Navigation;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Content\Related\RelatedIndex;
use YiiPress\Processor\ContentProcessorPipeline;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

use function array_slice;
use function ceil;
use function count;
use function dirname;
use function function_exists;
use function min;
use function pcntl_fork;

final readonly class ParallelEntryWriter
{
    private const int MIN_TASKS_PER_WORKER = 64;

    public function __construct(
        private ContentProcessorPipeline $pipeline,
        private TemplateResolver $templateResolver,
        private ?BuildCache $cache = null,
        private ?AssetFingerprintManifest $assetManifest = null,
        private ?RelatedIndex $relatedIndex = null,
        private ?TranslationIndex $translationIndex = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
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
        bool $noWrite = false,
    ): int {
        if ($tasks === []) {
            return 0;
        }

        $effectiveWorkerCount = $this->workerCountFor(count($tasks), $workerCount);

        if (!$noWrite) {
            $dirs = [];
            foreach ($tasks as $task) {
                $dirs[dirname($task['filePath'])] = true;
            }
            foreach ($dirs as $dirPath => $_) {
                if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
                }
            }
        }

        if ($effectiveWorkerCount <= 1) {
            $this->writeEntries($siteConfig, $tasks, $contentDir, $navigation, $crossRefResolver, $authors, $noWrite);
        } else {
            $this->writeParallel($siteConfig, $tasks, $contentDir, $effectiveWorkerCount, $navigation, $crossRefResolver, $authors, $noWrite);
        }

        return count($tasks);
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     */
    private function writeEntries(SiteConfig $siteConfig, array $tasks, string $contentDir, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors, bool $noWrite): void
    {
        $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors, $this->assetManifest, $this->relatedIndex, $this->translationIndex, $this->eventDispatcher);

        foreach ($tasks as $task) {
            $html = $renderer->render($siteConfig, $task['entry'], $task['permalink'], $navigation, $crossRefResolver, $task['navigationPager'] ?? null);
            if (!$noWrite) {
                FileWriter::write($task['filePath'], $html);
            }
        }
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, string $contentDir, int $workerCount, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors, bool $noWrite): void
    {
        $taskChunks = $this->partitionTasks($tasks, $workerCount);
        $pids = [];

        foreach ($taskChunks as $chunk) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Failed to fork worker process');
            }

            if ($pid === 0) {
                $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors, $this->assetManifest, $this->relatedIndex, $this->translationIndex, $this->eventDispatcher);

                foreach ($chunk as $task) {
                    $html = $renderer->render($siteConfig, $task['entry'], $task['permalink'], $navigation, $crossRefResolver, $task['navigationPager'] ?? null);
                    if (!$noWrite) {
                        FileWriter::write($task['filePath'], $html);
                    }
                }

                exit(0);
            }

            $pids[] = $pid;
        }

        $failed = false;
        $failure = null;
        foreach ($pids as $pid) {
            try {
                WorkerProcessStatus::waitFor($pid);
            } catch (RuntimeException $e) {
                $failed = true;
                $failure ??= $e;
            }
        }

        if ($failed) {
            throw new RuntimeException('One or more worker processes failed.', previous: $failure);
        }
    }

    public function workerCountFor(int $taskCount, int $requestedWorkerCount): int
    {
        if (!function_exists('pcntl_fork') || $requestedWorkerCount <= 1 || $taskCount < self::MIN_TASKS_PER_WORKER * 2) {
            return 1;
        }

        return min($requestedWorkerCount, $taskCount);
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     * @return list<list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}>>
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
