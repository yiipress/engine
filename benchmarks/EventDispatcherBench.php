<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;

#[Revs(100_000)]
#[Iterations(5)]
#[Warmup(1)]
final class EventDispatcherBench
{
    private ?EventDispatcherInterface $nullDispatcher = null;
    private EventDispatcherInterface $emptyDispatcher;
    private EventDispatcherInterface $dispatcher;
    private BenchHookEvent $event;

    public function __construct()
    {
        $this->event = new BenchHookEvent();
        $this->emptyDispatcher = new Dispatcher(new Provider(new ListenerCollection()));
        $this->dispatcher = new Dispatcher(new Provider(
            (new ListenerCollection())->add(static function (BenchHookEvent $event): void {})
        ));
    }

    public function benchNullDispatcherPath(): void
    {
        $this->nullDispatcher?->dispatch($this->event);
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

final readonly class BenchHookEvent
{
}
