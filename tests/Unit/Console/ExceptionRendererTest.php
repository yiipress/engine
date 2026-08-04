<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use YiiPress\Console\ExceptionRenderer;

final class ExceptionRendererTest extends TestCase
{
    /**
     * @return iterable<string, array{int, bool, bool, bool}>
     */
    public static function verbosityProvider(): iterable
    {
        yield 'default' => [OutputInterface::VERBOSITY_NORMAL, false, false, false];
        yield 'verbose' => [OutputInterface::VERBOSITY_VERBOSE, true, false, false];
        yield 'very verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, true, true, false];
        yield 'debug' => [OutputInterface::VERBOSITY_DEBUG, true, true, true];
    }

    #[DataProvider('verbosityProvider')]
    public function testRendersDetailsAccordingToVerbosity(
        int $verbosity,
        bool $hasExceptionClass,
        bool $hasLocation,
        bool $hasStackTrace,
    ): void {
        $output = new BufferedOutput($verbosity, false);

        (new ExceptionRenderer())->render(new RuntimeException('Something went wrong.'), $output);

        $rendered = $output->fetch();
        self::assertStringContainsString('Something went wrong.', $rendered);
        self::assertSame($hasExceptionClass, str_contains($rendered, 'Exception: RuntimeException'));
        self::assertSame($hasLocation, str_contains($rendered, 'Location:'));
        self::assertSame($hasStackTrace, str_contains($rendered, 'Stack trace:'));
        self::assertStringNotContainsString('In ExceptionRendererTest.php line', $rendered);
        self::assertSame($verbosity, $output->getVerbosity());
    }
}
