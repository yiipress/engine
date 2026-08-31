<?php

declare(strict_types=1);

namespace YiiPress\Console;

use YiiPress\Build\WorkerJobInterface;
use YiiPress\Build\CollectionListingWorkerJob;
use YiiPress\Build\CollectionListingWriter;
use YiiPress\Build\DateArchiveWorkerJob;
use YiiPress\Build\DateArchiveWriter;
use YiiPress\Build\EntryWriteWorkerJob;
use YiiPress\Build\ExecutableWorkerJobInterface;
use YiiPress\Build\FeedWorkerJob;
use YiiPress\Build\FeedWriter;
use YiiPress\Build\PageTemplateRenderer;
use YiiPress\Build\ParallelEntryWriter;
use YiiPress\Build\ProjectThemeDiscovery;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\ProjectProcessorConfigurator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function unserialize;

#[AsCommand(name: 'worker', description: 'Runs an internal portable worker job', hidden: true)]
final class WorkerCommand extends Command
{
    public function __construct(
        private readonly string $rootPath,
        private readonly ContentProcessorPipeline $contentPipeline,
        private readonly ContentProcessorPipeline $feedPipeline,
        private readonly ThemeRegistry $themeRegistry,
        private readonly TemplateResolver $templateResolver,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('job', InputArgument::REQUIRED);
        $this->addArgument('result', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $jobFile */
        $jobFile = $input->getArgument('job');
        /** @var string $resultFile */
        $resultFile = $input->getArgument('result');
        $contents = file_get_contents($jobFile);
        $job = $contents === false ? false : unserialize($contents, ['allowed_classes' => true]);
        if (!$job instanceof WorkerJobInterface) {
            $output->writeln('<error>Invalid worker job.</error>');
            return self::FAILURE;
        }

        $result = match (true) {
            $job instanceof EntryWriteWorkerJob => $this->runEntryWriteJob($job),
            $job instanceof DateArchiveWorkerJob => $this->runDateArchiveJob($job),
            $job instanceof CollectionListingWorkerJob => $this->runCollectionListingJob($job),
            $job instanceof FeedWorkerJob => $this->runFeedJob($job),
            $job instanceof ExecutableWorkerJobInterface => $job->run(),
            default => null,
        };

        if ($result === null) {
            $output->writeln('<error>Unsupported worker job.</error>');
            return self::FAILURE;
        }

        file_put_contents($resultFile, (string) $result, LOCK_EX);

        return self::SUCCESS;
    }

    private function runEntryWriteJob(EntryWriteWorkerJob $job): int
    {
        $this->registerThemes($job->contentDir());
        $this->configureProcessors($job->contentDir(), $job->siteConfig());

        $writer = new ParallelEntryWriter(
            $this->contentPipeline,
            $this->templateResolver,
            $job->cache(),
            $job->assetManifest(),
            $job->relatedIndex(),
            $job->translationIndex(),
            $this->eventDispatcher,
        );
        $writer->writeChunk($job->siteConfig(), $job->tasks(), $job->contentDir(), $job->navigation(), $job->crossRefResolver(), $job->authors(), $job->noWrite());

        return count($job->tasks());
    }

    private function runDateArchiveJob(DateArchiveWorkerJob $job): int
    {
        $this->registerThemes($job->contentDir());

        $renderer = new PageTemplateRenderer($this->templateResolver, $job->siteConfig()->theme, $job->assetManifest(), $job->siteConfig()->minify);
        $writer = new DateArchiveWriter($this->templateResolver, $job->assetManifest());

        $count = 0;
        foreach ($job->tasks() as $task) {
            $count += $writer->writeTask($task, $renderer, $job->siteConfig(), $job->collection(), $job->outputDir(), $job->navigation(), $job->noWrite());
        }

        return $count;
    }

    private function runCollectionListingJob(CollectionListingWorkerJob $job): int
    {
        $this->registerThemes($job->contentDir());

        $renderer = new PageTemplateRenderer($this->templateResolver, $job->siteConfig()->theme, $job->assetManifest(), $job->siteConfig()->minify);
        $writer = new CollectionListingWriter($this->templateResolver, $job->assetManifest());

        $count = 0;
        foreach ($job->tasks() as $task) {
            $count += $writer->writeTask($task, $renderer, $job->siteConfig(), $job->collection(), $job->navigation(), $job->noWrite());
        }

        return $count;
    }

    private function runFeedJob(FeedWorkerJob $job): int
    {
        $this->configureProcessors($job->contentDir(), $job->siteConfig());

        $writer = new FeedWriter($this->feedPipeline, $job->authors());

        $count = 0;
        foreach ($job->tasks() as $task) {
            $count += $writer->writeTask($task, $job->siteConfig(), $job->outputDir(), $job->noWrite());
        }

        return $count;
    }

    private function registerThemes(string $contentDir): void
    {
        new ProjectThemeDiscovery()->register($this->themeRegistry, $this->rootPath . '/themes');
        $localTemplatesDir = $contentDir . '/templates';
        if (is_dir($localTemplatesDir)) {
            $this->themeRegistry->register(new Theme('local', $localTemplatesDir));
        }
    }

    private function configureProcessors(string $contentDir, SiteConfig $siteConfig): void
    {
        new ProjectProcessorConfigurator($this->contentPipeline, $this->feedPipeline)->configure($contentDir, $siteConfig);
    }
}
