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
            hardbreaks: $config->hardSoftBreaks,
            unsafe: !$config->noHtmlBlocks || !$config->noHtmlSpans,
            footnotes: false,
            tables: $config->tables,
            strikethrough: $config->strikethrough,
            tasklist: $config->tasklists,
            autolink: $config->urlAutolinks || $config->emailAutolinks || $config->wwwAutolinks,
            noIndentedCodeBlocks: $config->noIndentedCodeBlocks,
            permissiveAtxHeadings: $config->permissiveAtxHeaders,
            collapseWhitespace: $config->collapseWhitespace,
            underline: $config->underline,
            latexMath: $config->latexMath,
            wikiLinks: $config->wikilinks,
            admonitions: $config->admonitions,
            insert: $config->insert,
        ));
    }

    public function render(string $markdown): string
    {
        return $this->renderer->toHtml($markdown);
    }
}
