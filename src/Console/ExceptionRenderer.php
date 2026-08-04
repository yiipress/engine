<?php

declare(strict_types=1);

namespace YiiPress\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ExceptionRenderer
{
    public function render(Throwable $throwable, OutputInterface $output): void
    {
        $verbosity = $output->getVerbosity();
        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        try {
            (new Application())->renderThrowable($throwable, $output);
        } finally {
            $output->setVerbosity($verbosity);
        }

        $output->writeln(
            '<comment>Exception:</comment> ' . OutputFormatter::escape($throwable::class),
            OutputInterface::VERBOSITY_VERBOSE,
        );
        $output->writeln(
            sprintf(
                '<comment>Location:</comment> %s:%d',
                OutputFormatter::escape($throwable->getFile()),
                $throwable->getLine(),
            ),
            OutputInterface::VERBOSITY_VERY_VERBOSE,
        );
        $output->writeln('<comment>Stack trace:</comment>', OutputInterface::VERBOSITY_DEBUG);
        $output->writeln(
            $throwable->getTraceAsString(),
            OutputInterface::VERBOSITY_DEBUG | OutputInterface::OUTPUT_RAW,
        );
    }
}
