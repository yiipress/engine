<?php

declare(strict_types=1);

namespace YiiPress\Hook;

use Closure;

use function array_key_exists;
use function count;

final class HookDispatcher
{
    /**
     * @var array<string, list<HookInterface|Closure(HookEventInterface): void>>
     */
    private array $listeners;

    /**
     * @param array<string, list<HookInterface|callable(HookEventInterface): void>> $listeners
     */
    public function __construct(array $listeners = [])
    {
        $this->listeners = [];

        foreach ($listeners as $eventName => $eventListeners) {
            foreach ($eventListeners as $listener) {
                $this->listen($eventName, $listener);
            }
        }
    }

    /**
     * @param HookInterface|callable(HookEventInterface): void $listener
     */
    public function listen(string $eventName, HookInterface|callable $listener): void
    {
        if (!$listener instanceof HookInterface && !$listener instanceof Closure) {
            $listener = Closure::fromCallable($listener);
        }

        $this->listeners[$eventName][] = $listener;
    }

    public function hasListeners(string $eventName): bool
    {
        return array_key_exists($eventName, $this->listeners) && count($this->listeners[$eventName]) > 0;
    }

    public function dispatch(HookEventInterface $event): void
    {
        $eventName = $event->name();
        if (!$this->hasListeners($eventName)) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if ($listener instanceof HookInterface) {
                $listener->handle($event);
            } else {
                $listener($event);
            }
        }
    }
}
