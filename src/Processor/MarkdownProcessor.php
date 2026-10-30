<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Render\MarkdownRenderer;

final class MarkdownProcessor implements ContentProcessorInterface, SiteConfigAwareProcessorInterface
{
    public function __construct(
        private MarkdownRenderer $renderer,
    ) {}

    public function applySiteConfig(SiteConfig $siteConfig): void
    {
        $this->renderer = new MarkdownRenderer($siteConfig->markdown);
    }

    public function process(string $content, Entry $entry): string
    {
        return $this->renderer->render($content);
    }
}
