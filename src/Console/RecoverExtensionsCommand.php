<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Extension\Transaction\MutationLock;
use YiiPress\Extension\Transaction\Recovery;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'recover:extensions',
    description: 'Finishes or undoes an interrupted extension installation',
)]
final class RecoverExtensionsCommand extends Command
{
    public function __construct(
        private readonly string $rootPath,
        private readonly Recovery $recovery = new Recovery(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = MutationLock::acquire($this->rootPath);
        try {
            $result = $this->recovery->recover($this->rootPath);
        } finally {
            $lock->release();
        }

        $output->writeln($result ?? 'No interrupted extension operation found.');

        return ExitCode::OK;
    }
}
