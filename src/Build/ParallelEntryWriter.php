<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\CrossReferenceResolver;
use App\Content\Model\Entry;
use App\Content\Model\Navigation;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use RuntimeException;

use function pcntl_fork;
use function pcntl_waitpid;

final class ParallelEntryWriter
{
    public function __construct(
        private readonly ContentProcessorPipeline $pipeline,
        private readonly TemplateResolver $templateResolver,
        private readonly ?BuildCache $cache = null,
    ) {}

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
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

        $dirs = [];
        foreach ($tasks as $task) {
            $dirs[dirname($task['filePath'])] = true;
        }
        foreach ($dirs as $dirPath => $_) {
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0o755, true);
            }
        }

        if ($workerCount <= 1) {
            $this->writeEntries($siteConfig, $tasks, $contentDir, $navigation, $crossRefResolver, $authors);
        } else {
            $this->writeParallel($siteConfig, $tasks, $contentDir, $workerCount, $navigation, $crossRefResolver, $authors);
        }

        return count($tasks);
    }

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
     */
    private function writeEntries(SiteConfig $siteConfig, array $tasks, string $contentDir, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors): void
    {
        $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors);

        foreach ($tasks as $task) {
            file_put_contents($task['filePath'], $renderer->render($siteConfig, $task['entry'], $navigation, $crossRefResolver));
        }
    }

    /**
     * @param list<array{entry: Entry, filePath: string}> $tasks
     */
    private function writeParallel(SiteConfig $siteConfig, array $tasks, string $contentDir, int $workerCount, ?Navigation $navigation, ?CrossReferenceResolver $crossRefResolver, array $authors): int
    {
        $totalEntries = count($tasks);
        $pids = [];

        for ($workerIndex = 0; $workerIndex < $workerCount; $workerIndex++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Failed to fork worker process');
            }

            if ($pid === 0) {
                $renderer = new EntryRenderer($this->pipeline, $this->templateResolver, $this->cache, $contentDir, $authors);

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
