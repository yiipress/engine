<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;
use App\Highlighter\SyntaxHighlighter;
use RuntimeException;

final readonly class SyntaxHighlightProcessor implements ContentProcessorInterface
{
    public function __construct(
        private SyntaxHighlighter $highlighter,
    ) {}

    public function process(string $content, Entry $entry): string
    {
        try {
            return $this->highlighter->highlight($content);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to highlight code in entry \"{$entry->title}\".", 0, $e);
        }
    }
}
