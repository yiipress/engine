<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Highlighter\SyntaxHighlighter;

final class SyntaxHighlightProcessor implements ContentProcessorInterface
{
    private SyntaxHighlighter $highlighter;

    public function __construct()
    {
        $this->highlighter = new SyntaxHighlighter();
    }

    public function process(string $content, Entry $entry): string
    {
        return $this->highlighter->highlight($content);
    }
}
