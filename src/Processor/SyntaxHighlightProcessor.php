<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Highlighter\SyntaxHighlighter;

final class SyntaxHighlightProcessor implements ContentProcessorInterface
{
    public function __construct(
        private readonly SyntaxHighlighter $highlighter,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        return $this->highlighter->highlight($content);
    }
}
