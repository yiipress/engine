<?php

declare(strict_types=1);

namespace YiiPress\Console;

use function defined;
use function function_exists;

final readonly class ServeRuntimeCapabilities
{
    private string $directorySeparator;
    private bool $sigintDefined;
    private bool $sigtermDefined;
    private bool $pcntlAsyncSignalsAvailable;
    private bool $pcntlForkAvailable;
    private bool $pcntlSignalDispatchAvailable;
    private bool $pcntlSignalAvailable;
    private bool $pcntlWaitAvailable;
    private bool $pcntlWaitStatusAvailable;
    private bool $posixKillAvailable;

    public function __construct(
        ?string $directorySeparator = null,
        ?bool $sigintDefined = null,
        ?bool $sigtermDefined = null,
        ?bool $pcntlAsyncSignalsAvailable = null,
        ?bool $pcntlForkAvailable = null,
        ?bool $pcntlSignalDispatchAvailable = null,
        ?bool $pcntlSignalAvailable = null,
        ?bool $pcntlWaitAvailable = null,
        ?bool $pcntlWaitStatusAvailable = null,
        ?bool $posixKillAvailable = null,
    ) {
        $this->directorySeparator = $directorySeparator ?? \DIRECTORY_SEPARATOR;
        $this->sigintDefined = $sigintDefined ?? defined('SIGINT');
        $this->sigtermDefined = $sigtermDefined ?? defined('SIGTERM');
        $this->pcntlAsyncSignalsAvailable = $pcntlAsyncSignalsAvailable ?? function_exists('pcntl_async_signals');
        $this->pcntlForkAvailable = $pcntlForkAvailable ?? function_exists('pcntl_fork');
        $this->pcntlSignalDispatchAvailable = $pcntlSignalDispatchAvailable
            ?? function_exists('pcntl_signal_dispatch');
        $this->pcntlSignalAvailable = $pcntlSignalAvailable ?? function_exists('pcntl_signal');
        $this->pcntlWaitAvailable = $pcntlWaitAvailable ?? function_exists('pcntl_wait');
        $this->pcntlWaitStatusAvailable = $pcntlWaitStatusAvailable
            ?? (
                function_exists('pcntl_wexitstatus')
                && function_exists('pcntl_wifexited')
                && function_exists('pcntl_wifsignaled')
            );
        $this->posixKillAvailable = $posixKillAvailable ?? function_exists('posix_kill');
    }

    public function supportsEventLoopSignals(): bool
    {
        return $this->sigintDefined
            && $this->sigtermDefined
            && $this->pcntlSignalAvailable
            && $this->pcntlSignalDispatchAvailable;
    }

    public function supportsWorkerPool(): bool
    {
        return $this->directorySeparator !== '\\'
            && $this->supportsEventLoopSignals()
            && $this->pcntlAsyncSignalsAvailable
            && $this->pcntlForkAvailable
            && $this->pcntlSignalAvailable
            && $this->pcntlWaitAvailable
            && $this->pcntlWaitStatusAvailable
            && $this->posixKillAvailable;
    }
}
