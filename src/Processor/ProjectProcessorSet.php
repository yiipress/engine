<?php

declare(strict_types=1);

namespace YiiPress\Processor;

final readonly class ProjectProcessorSet
{
    /**
     * @param list<ContentProcessorInterface> $contentBeforeMarkdown
     * @param list<ContentProcessorInterface> $contentAfterMarkdown
     * @param list<ContentProcessorInterface> $feedBeforeMarkdown
     * @param list<ContentProcessorInterface> $feedAfterMarkdown
     */
    public function __construct(
        public array $contentBeforeMarkdown = [],
        public array $contentAfterMarkdown = [],
        public array $feedBeforeMarkdown = [],
        public array $feedAfterMarkdown = [],
    ) {}
}
