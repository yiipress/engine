<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Hook;

use YiiPress\Hook\HookDispatcher;
use YiiPress\Hook\HookEventInterface;
use YiiPress\Hook\HookInterface;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class HookDispatcherTest extends TestCase
{
    public function testDispatchesClosureListenersInOrder(): void
    {
        $event = new TestHookEvent('test.event');
        $calls = [];
        $dispatcher = new HookDispatcher([
            'test.event' => [
                static function (HookEventInterface $event) use (&$calls): void {
                    $calls[] = 'first:' . $event->name();
                },
                static function (HookEventInterface $event) use (&$calls): void {
                    $calls[] = 'second:' . $event->name();
                },
            ],
        ]);

        $dispatcher->dispatch($event);

        assertSame(['first:test.event', 'second:test.event'], $calls);
    }

    public function testDispatchesHookInterfaceListeners(): void
    {
        $event = new TestHookEvent('test.event');
        $listener = new TestHookListener();
        $dispatcher = new HookDispatcher();
        $dispatcher->listen('test.event', $listener);

        $dispatcher->dispatch($event);

        assertSame(['test.event'], $listener->calls);
    }

    public function testIgnoresEventsWithoutListeners(): void
    {
        $dispatcher = new HookDispatcher();

        assertSame(false, $dispatcher->hasListeners('missing.event'));
        $dispatcher->dispatch(new TestHookEvent('missing.event'));
    }
}

final readonly class TestHookEvent implements HookEventInterface
{
    public function __construct(private string $name) {}

    public function name(): string
    {
        return $this->name;
    }
}

final class TestHookListener implements HookInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function handle(HookEventInterface $event): void
    {
        $this->calls[] = $event->name();
    }
}
