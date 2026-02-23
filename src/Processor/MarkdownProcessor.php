<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Render\MarkdownRenderer;

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
