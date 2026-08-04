<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Update\SelfUpdater;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(name: 'self-update', description: 'Updates YiiPress to the latest stable or nightly build')]
final class SelfUpdateCommand extends Command
{
    public function __construct(private readonly SelfUpdater $updater)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('nightly', null, InputOption::VALUE_NONE, 'Install the latest nightly build');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nightly = (bool) $input->getOption('nightly');
        $output->writeln('Downloading the latest ' . ($nightly ? 'nightly' : 'stable') . ' build...');
        $version = $this->updater->update($nightly);
        $output->writeln("<info>YiiPress was updated successfully ($version).</info>");

        return ExitCode::OK;
    }
}
