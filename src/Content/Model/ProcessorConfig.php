<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

final readonly class ProcessorConfig
{
    /**
     * @param list<string> $contentBeforeMarkdown
     * @param list<string> $contentAfterMarkdown
     * @param list<string> $feedBeforeMarkdown
     * @param list<string> $feedAfterMarkdown
     */
    public function __construct(
        public bool $discover = true,
        public array $contentBeforeMarkdown = [],
        public array $contentAfterMarkdown = [],
        public array $feedBeforeMarkdown = [],
        public array $feedAfterMarkdown = [],
    ) {}
}
