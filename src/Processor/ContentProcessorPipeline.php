<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;

final class ContentProcessorPipeline
{
    /** @var list<ContentProcessorInterface> */
    private array $processors;

    public function __construct(ContentProcessorInterface ...$processors)
    {
        $this->processors = array_values($processors);
    }

    public function process(string $content, Entry $entry): string
    {
        foreach ($this->processors as $processor) {
            $content = $processor->process($content, $entry);
        }

        return $content;
    }
}
