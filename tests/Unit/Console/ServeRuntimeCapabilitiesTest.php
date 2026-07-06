<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YiiPress\Console\ServeRuntimeCapabilities;

final class ServeRuntimeCapabilitiesTest extends TestCase
{
    #[Test]
    public function windowsRuntimeDoesNotSupportWorkerPoolOrEventLoopSignals(): void
    {
        $capabilities = new ServeRuntimeCapabilities(
            directorySeparator: '\\',
            sigintDefined: false,
            sigtermDefined: false,
            pcntlAsyncSignalsAvailable: true,
            pcntlForkAvailable: true,
            pcntlSignalDispatchAvailable: true,
            pcntlSignalAvailable: true,
            pcntlWaitAvailable: true,
            pcntlWaitStatusAvailable: true,
            posixKillAvailable: true,
        );

        self::assertFalse($capabilities->supportsWorkerPool());
        self::assertFalse($capabilities->supportsEventLoopSignals());
    }

    #[Test]
    public function missingSignalConstantsDisableServeSignalHooks(): void
    {
        $capabilities = new ServeRuntimeCapabilities(
            directorySeparator: '/',
            sigintDefined: false,
            sigtermDefined: true,
            pcntlAsyncSignalsAvailable: true,
            pcntlForkAvailable: true,
            pcntlSignalDispatchAvailable: true,
            pcntlSignalAvailable: true,
            pcntlWaitAvailable: true,
            pcntlWaitStatusAvailable: true,
            posixKillAvailable: true,
        );

        self::assertFalse($capabilities->supportsWorkerPool());
        self::assertFalse($capabilities->supportsEventLoopSignals());
    }

    #[Test]
    public function unixRuntimeWithPcntlAndSignalsSupportsWorkerPool(): void
    {
        $capabilities = new ServeRuntimeCapabilities(
            directorySeparator: '/',
            sigintDefined: true,
            sigtermDefined: true,
            pcntlAsyncSignalsAvailable: true,
            pcntlForkAvailable: true,
            pcntlSignalDispatchAvailable: true,
            pcntlSignalAvailable: true,
            pcntlWaitAvailable: true,
            pcntlWaitStatusAvailable: true,
            posixKillAvailable: true,
        );

        self::assertTrue($capabilities->supportsWorkerPool());
        self::assertTrue($capabilities->supportsEventLoopSignals());
    }

    #[Test]
    public function missingPcntlFunctionsDisableWorkerPoolOnly(): void
    {
        $capabilities = new ServeRuntimeCapabilities(
            directorySeparator: '/',
            sigintDefined: true,
            sigtermDefined: true,
            pcntlAsyncSignalsAvailable: false,
            pcntlForkAvailable: true,
            pcntlSignalDispatchAvailable: true,
            pcntlSignalAvailable: true,
            pcntlWaitAvailable: true,
            pcntlWaitStatusAvailable: true,
            posixKillAvailable: true,
        );

        self::assertFalse($capabilities->supportsWorkerPool());
        self::assertTrue($capabilities->supportsEventLoopSignals());
    }

    #[Test]
    public function missingPcntlSignalDispatchDisablesEventLoopSignals(): void
    {
        $capabilities = new ServeRuntimeCapabilities(
            directorySeparator: '/',
            sigintDefined: true,
            sigtermDefined: true,
            pcntlAsyncSignalsAvailable: true,
            pcntlForkAvailable: true,
            pcntlSignalDispatchAvailable: false,
            pcntlSignalAvailable: true,
            pcntlWaitAvailable: true,
            pcntlWaitStatusAvailable: true,
            posixKillAvailable: true,
        );

        self::assertFalse($capabilities->supportsWorkerPool());
        self::assertFalse($capabilities->supportsEventLoopSignals());
    }
}
