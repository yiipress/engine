<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\CrossReferenceResolver;
use YiiPress\Content\I18n\TranslationIndex;
use YiiPress\Content\Model\Author;
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
        private ?PortableWorkerPool $workerPool = null,
    ) {}

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     * @param array<string, Author> $authors
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

        if ($effectiveWorkerCount <= 1) {
            $this->writeChunk($siteConfig, $tasks, $contentDir, $navigation, $crossRefResolver, $authors, $noWrite);
        } else {
            $this->writeParallel($siteConfig, $tasks, $contentDir, $effectiveWorkerCount, $navigation, $crossRefResolver, $authors, $noWrite);
        }

        return count($tasks);
    }

    /**
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     * @param array<string, Author> $authors
     */
    public function writeChunk(SiteConfig $siteConfig, array $tasks, string $contentDir, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors, bool $noWrite): void
    {
        if (!$noWrite) {
            $dirs = [];
            foreach ($tasks as $task) {
                $dirs[dirname($task['filePath'])] = true;
            }
            foreach ($dirs as $dirPath => $_) {
                // Another worker may create a shared directory between the check and mkdir().
                if (!is_dir($dirPath) && !@mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
                }
            }
        }

        $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors, $this->assetManifest, $this->relatedIndex, $this->translationIndex, $this->eventDispatcher);

        foreach ($tasks as $task) {
            $html = $renderer->render($siteConfig, $task['entry'], $task['permalink'], $navigation, $crossRefResolver, $task['navigationPager'] ?? null);
            if (!$noWrite) {
                FileWriter::write($task['filePath'], $html);
            }
        }
    }

    /**
     * Renders and writes entries across independent worker processes. pcntl_fork() would
     * duplicate the running PHAR's file descriptor, so forked workers would share its read
     * offset: two workers autoloading a class for the first time at once would race on that
     * offset and corrupt the decompression (phar crc32 mismatch, or a class body full of
     * another file's bytes). Spawning independent processes instead gives each one its own
     * file descriptor, so there's nothing to race on.
     *
     * @param list<array{entry: Entry, filePath: string, permalink: string, navigationPager?: array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null}|null}> $tasks
     * @param array<string, Author> $authors
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, string $contentDir, int $workerCount, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors, bool $noWrite): void
    {
        $taskChunks = $this->partitionTasks($tasks, $workerCount);
        $jobs = [];
        foreach ($taskChunks as $chunk) {
            $jobs[] = new EntryWriteWorkerJob($siteConfig, $chunk, $contentDir, $navigation, $crossRefResolver, $authors, $noWrite, $this->cache, $this->assetManifest, $this->relatedIndex, $this->translationIndex);
        }
        ($this->workerPool ?? new PortableWorkerPool())->run($jobs);
    }

    public function workerCountFor(int $taskCount, int $requestedWorkerCount): int
    {
        if (!$this->supportsParallelExecution() || $requestedWorkerCount <= 1 || $taskCount < self::MIN_TASKS_PER_WORKER * 2) {
            return 1;
        }

        return min($requestedWorkerCount, $taskCount);
    }

    private function supportsParallelExecution(): bool
    {
        return function_exists('proc_open');
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
