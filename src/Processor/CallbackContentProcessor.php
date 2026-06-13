<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use Closure;
use YiiPress\Content\Model\Entry;

final readonly class CallbackContentProcessor implements ContentProcessorInterface
{
    public function __construct(
        private Closure $callback,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        return (string) ($this->callback)($content, $entry);
    }
}
