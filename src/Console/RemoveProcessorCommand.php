<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lifecycle\ExtensionRemover;
use Yiisoft\Yii\Console\ExitCode;

use function sprintf;

#[AsCommand(
    name: 'remove:processor',
    description: 'Removes an installed content processor that the processors setting no longer lists',
)]
final class RemoveProcessorCommand extends Command
{
    public function __construct(private readonly string $rootPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Processor ID from yiipress.lock, for example acme/toc')
            ->addUsage('acme/toc');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = ExtensionIdArgument::resolve($input);
            $result = ExtensionRemover::forProcessor()->remove($this->rootPath, $id);
        } catch (InstallException $exception) {
            return ExtensionIdArgument::fail($output, $exception);
        }

        if ($result->recovered !== null) {
            $output->writeln($result->recovered);
        }
        $output->writeln(sprintf(
            $result->deletedDirectory
                ? 'Removed %s %s: deleted %s and its entry in yiipress.lock.'
                : 'Removed %s %s: %s was already gone; deleted its entry in yiipress.lock.',
            $id,
            $result->removed->version,
            $result->removed->destination,
        ));

        return ExitCode::OK;
    }
}
