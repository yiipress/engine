<?php

declare(strict_types=1);

namespace YiiPress\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Render\MarkdownRenderer;

final readonly class MarkdownProcessor implements ContentProcessorInterface
{
    public function __construct(
        private MarkdownRenderer $renderer,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        return $this->renderer->render($content);
    }
}
