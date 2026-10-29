<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Lifecycle\ThemeRemover;
use Yiisoft\Yii\Console\ExitCode;

use function sprintf;

#[AsCommand(
    name: 'remove:theme',
    description: 'Removes an installed theme that the site and its content no longer use',
)]
final class RemoveThemeCommand extends Command
{
    public function __construct(private readonly string $rootPath)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Theme ID from yiipress.lock, for example yiipress/wonder')
            ->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Directory of config.yaml', 'content')
            ->addUsage('yiipress/wonder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var string $contentDir */
            $contentDir = $input->getOption('content-dir');
            $id = ExtensionIdArgument::resolve($input);
            $result = new ThemeRemover()->remove($this->rootPath, $id, $contentDir);
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
