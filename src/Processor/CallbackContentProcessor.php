<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use Closure;
use RuntimeException;
use YiiPress\Content\Model\Entry;

use function get_debug_type;
use function sprintf;

final readonly class CallbackContentProcessor implements ContentProcessorInterface
{
    public function __construct(
        private Closure $callback,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        $result = ($this->callback)($content, $entry);
        if (!is_string($result)) {
            throw new RuntimeException(sprintf(
                'Project processor callback must return string, %s returned.',
                get_debug_type($result),
            ));
        }

        return $result;
    }
}
