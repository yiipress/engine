<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Hook\HookDispatcher;
use YiiPress\Hook\HookEventInterface;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Revs(100_000)]
#[Iterations(5)]
#[Warmup(1)]
final class HookDispatcherBench
{
    private HookDispatcher $emptyDispatcher;
    private HookDispatcher $dispatcher;
    private HookEventInterface $event;

    public function __construct()
    {
        $this->event = new BenchHookEvent();
        $this->emptyDispatcher = new HookDispatcher();
        $this->dispatcher = new HookDispatcher([
            BenchHookEvent::NAME => [
                static function (HookEventInterface $event): void {},
            ],
        ]);
    }

    public function benchHasListenersWithoutListeners(): void
    {
        $this->emptyDispatcher->hasListeners(BenchHookEvent::NAME);
    }

    public function benchDispatchWithoutListeners(): void
    {
        $this->emptyDispatcher->dispatch($this->event);
    }

    public function benchDispatchWithSingleListener(): void
    {
        $this->dispatcher->dispatch($this->event);
    }
}

final readonly class BenchHookEvent implements HookEventInterface
{
    public const string NAME = 'bench.event';

    public function name(): string
    {
        return self::NAME;
    }
}
