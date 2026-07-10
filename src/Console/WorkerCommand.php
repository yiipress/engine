<?php

declare(strict_types=1);

namespace YiiPress\Console;

use YiiPress\Build\WorkerJobInterface;
use YiiPress\Build\EntryWriteWorkerJob;
use YiiPress\Build\ExecutableWorkerJobInterface;
use YiiPress\Build\ParallelEntryWriter;
use YiiPress\Build\ProjectThemeDiscovery;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
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

#[AsCommand(name: '_worker', description: 'Runs an internal portable worker job', hidden: true)]
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
        $jobFile = (string) $input->getArgument('job');
        $resultFile = (string) $input->getArgument('result');
        $contents = file_get_contents($jobFile);
        $job = $contents === false ? false : unserialize($contents, ['allowed_classes' => true]);
        if (!$job instanceof WorkerJobInterface) {
            $output->writeln('<error>Invalid worker job.</error>');
            return self::FAILURE;
        }

        if (!$job instanceof EntryWriteWorkerJob && !$job instanceof ExecutableWorkerJobInterface) {
            $output->writeln('<error>Unsupported worker job.</error>');
            return self::FAILURE;
        }

        $result = $job instanceof EntryWriteWorkerJob ? $this->runEntryWriteJob($job) : $job->run();
        file_put_contents($resultFile, (string) $result, LOCK_EX);

        return self::SUCCESS;
    }

    private function runEntryWriteJob(EntryWriteWorkerJob $job): int
    {
        (new ProjectThemeDiscovery())->register($this->themeRegistry, $this->rootPath . '/themes');
        $localTemplatesDir = $job->contentDir() . '/templates';
        if (is_dir($localTemplatesDir)) {
            $this->themeRegistry->register(new Theme('local', $localTemplatesDir));
        }

        (new ProjectProcessorConfigurator($this->contentPipeline, $this->feedPipeline))->configure($job->contentDir(), $job->siteConfig());

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
}
