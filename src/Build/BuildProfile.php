<?php

declare(strict_types=1);

namespace App\Build;

use function array_sum;
use function hrtime;

final class BuildProfile
{
    /** @var array<string, int> */
    private array $durations = [];
    private ?string $currentPhase = null;
    private int $phaseStartedAt = 0;

    public function __construct(
        private readonly bool $enabled,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function start(string $phase): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->currentPhase = $phase;
        $this->phaseStartedAt = hrtime(true);
    }

    public function switchTo(string $phase): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->stop();
        $this->start($phase);
    }

    public function stop(): void
    {
        if (!$this->enabled || $this->currentPhase === null) {
            return;
        }

        $this->durations[$this->currentPhase] = ($this->durations[$this->currentPhase] ?? 0)
            + hrtime(true) - $this->phaseStartedAt;
        $this->currentPhase = null;
        $this->phaseStartedAt = 0;
    }

    /**
     * @return array<string, float>
     */
    public function phases(): array
    {
        $phases = $this->durations;
        if ($this->enabled && $this->currentPhase !== null) {
            $phases[$this->currentPhase] = ($phases[$this->currentPhase] ?? 0) + hrtime(true) - $this->phaseStartedAt;
        }

        $result = [];
        foreach ($phases as $phase => $nanoseconds) {
            $result[$phase] = $nanoseconds / 1_000_000_000;
        }

        return $result;
    }

    public function totalSeconds(): float
    {
        return array_sum($this->phases());
    }
}
