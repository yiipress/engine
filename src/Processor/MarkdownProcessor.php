<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Render\MarkdownRenderer;

final class MarkdownProcessor implements ContentProcessorInterface
{
    private MarkdownRenderer $renderer;

    public function __construct(MarkdownConfig $config = new MarkdownConfig())
    {
        $this->renderer = new MarkdownRenderer($config);
    }

    public function process(string $content, Entry $entry): string
    {
        return $this->renderer->render($content);
    }
}
