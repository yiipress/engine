<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\SiteConfig;

final readonly class ProjectProcessorConfigurator
{
    public function __construct(
        private ContentProcessorPipeline $contentPipeline,
        private ContentProcessorPipeline $feedPipeline,
    ) {}

    public function configure(string $contentDir, SiteConfig $siteConfig): void
    {
        $processors = (new ProjectProcessorLoader($contentDir, $contentDir . '/config.yaml'))->load($siteConfig->processors);

        $this->contentPipeline->reset();
        $this->feedPipeline->reset();
        $this->contentPipeline->insertBefore(MarkdownProcessor::class, ...$processors->contentBeforeMarkdown);
        $this->contentPipeline->insertAfter(MarkdownProcessor::class, ...$processors->contentAfterMarkdown);
        $this->feedPipeline->insertBefore(MarkdownProcessor::class, ...$processors->feedBeforeMarkdown);
        $this->feedPipeline->insertAfter(MarkdownProcessor::class, ...$processors->feedAfterMarkdown);
        $this->contentPipeline->applySiteConfig($siteConfig);
        $this->feedPipeline->applySiteConfig($siteConfig);
    }
}
