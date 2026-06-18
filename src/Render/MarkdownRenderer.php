<?php

declare(strict_types=1);

namespace YiiPress\Render;

use MdParser\Options;
use MdParser\Parser;
use YiiPress\Content\Model\MarkdownConfig;

final class MarkdownRenderer
{
    private Parser $renderer;

    public function __construct(MarkdownConfig $config = new MarkdownConfig())
    {
        $this->renderer = new Parser(new Options(
            tables: $config->tables,
            strikethrough: $config->strikethrough,
            tasklist: $config->tasklists,
            autolink: $config->urlAutolinks || $config->emailAutolinks || $config->wwwAutolinks,
            collapseWhitespace: $config->collapseWhitespace,
            latexMath: $config->latexMath,
            wikiLinks: $config->wikilinks,
            underline: $config->underline,
            unsafe: !$config->noHtmlBlocks || !$config->noHtmlSpans,
            permissiveAtxHeadings: $config->permissiveAtxHeaders,
            noIndentedCodeBlocks: $config->noIndentedCodeBlocks,
            hardbreaks: $config->hardSoftBreaks,
            footnotes: false,
        ));
    }

    public function render(string $markdown): string
    {
        return $this->renderer->toHtml($markdown);
    }
}
