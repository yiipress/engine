<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Console\ConsoleOutputConfigurator;

final class ConsoleOutputConfiguratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function verbosityProvider(): iterable
    {
        yield 'silent' => ['--silent', OutputInterface::VERBOSITY_SILENT];
        yield 'quiet' => ['-q', OutputInterface::VERBOSITY_QUIET];
        yield 'long quiet' => ['--quiet', OutputInterface::VERBOSITY_QUIET];
        yield 'verbose' => ['-v', OutputInterface::VERBOSITY_VERBOSE];
        yield 'long verbose' => ['--verbose', OutputInterface::VERBOSITY_VERBOSE];
        yield 'long verbose 1' => ['--verbose=1', OutputInterface::VERBOSITY_VERBOSE];
        yield 'very verbose' => ['-vv', OutputInterface::VERBOSITY_VERY_VERBOSE];
        yield 'long verbose 2' => ['--verbose=2', OutputInterface::VERBOSITY_VERY_VERBOSE];
        yield 'debug' => ['-vvv', OutputInterface::VERBOSITY_DEBUG];
        yield 'long debug' => ['--verbose=3', OutputInterface::VERBOSITY_DEBUG];
    }

    #[DataProvider('verbosityProvider')]
    public function testConfiguresVerbosityBeforeApplicationStarts(string $option, int $verbosity): void
    {
        $input = new ArgvInput(['yii', 'build', $option]);
        $output = new BufferedOutput();

        (new ConsoleOutputConfigurator())->configure($input, $output);

        self::assertSame($verbosity, $output->getVerbosity());
    }

    public function testPreservesExistingVerbosityWithoutOption(): void
    {
        $input = new ArgvInput(['yii', 'build']);
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE);

        (new ConsoleOutputConfigurator())->configure($input, $output);

        self::assertSame(OutputInterface::VERBOSITY_VERY_VERBOSE, $output->getVerbosity());
    }
}
