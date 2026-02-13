<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class MarkdownConfig
{
    public function __construct(
        public bool $tables = true,
        public bool $strikethrough = true,
        public bool $tasklists = true,
        public bool $autolinks = true,
        public bool $collapseWhitespace = false,
        public bool $latexMath = false,
        public bool $wikilinks = false,
        public bool $underline = false,
        public bool $htmlBlocks = true,
        public bool $htmlSpans = true,
    ) {}
}
