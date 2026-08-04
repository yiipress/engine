<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleOutputConfigurator
{
    public function configure(ArgvInput $input, OutputInterface $output): void
    {
        $output->setVerbosity(match (true) {
            $input->hasParameterOption('--silent', true) => OutputInterface::VERBOSITY_SILENT,
            $input->hasParameterOption(['--quiet', '-q'], true) => OutputInterface::VERBOSITY_QUIET,
            $input->hasParameterOption(['-vvv', '--verbose=3'], true) => OutputInterface::VERBOSITY_DEBUG,
            $input->hasParameterOption(['-vv', '--verbose=2'], true) => OutputInterface::VERBOSITY_VERY_VERBOSE,
            $input->hasParameterOption(['-v', '--verbose', '--verbose=1'], true) => OutputInterface::VERBOSITY_VERBOSE,
            default => $output->getVerbosity(),
        });
    }
}
