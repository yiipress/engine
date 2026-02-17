<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Render\MarkdownRenderer;

final class MarkdownProcessor implements ContentProcessorInterface
{
    public function __construct(
        private readonly MarkdownRenderer $renderer,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        return $this->renderer->render($content);
    }
}
