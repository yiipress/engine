<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'serve-worker', description: 'Runs an internal serve worker on an inherited socket', hidden: true)]
final class ServeWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('fd', InputArgument::REQUIRED)
            ->addArgument('address', InputArgument::REQUIRED)
            ->addArgument('content-dir', InputArgument::REQUIRED)
            ->addArgument('output-dir', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $fd */
        $fd = $input->getArgument('fd');
        /** @var string $address */
        $address = $input->getArgument('address');
        /** @var string $contentDir */
        $contentDir = $input->getArgument('content-dir');
        /** @var string $outputDir */
        $outputDir = $input->getArgument('output-dir');

        return new ServeCommand()->runFromInheritedSocket((int) $fd, $address, $contentDir, $outputDir);
    }
}
