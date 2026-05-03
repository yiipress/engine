<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;

/**
 * Transforms content during the rendering pipeline.
 *
 * Processors are chained via {@see ContentProcessorPipeline},
 * where each processor receives the output of the previous one.
 */
interface ContentProcessorInterface
{
    public function process(string $content, Entry $entry): string;
}
