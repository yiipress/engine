<?php

declare(strict_types=1);

namespace YiiPress\Render;

use YiiPress\Content\Model\MarkdownConfig;
use YiiPress\Markdown\MarkdownOptions;
use YiiPress\Markdown\MarkdownRenderer as NativeMarkdownRenderer;

final class MarkdownRenderer
{
    private NativeMarkdownRenderer $renderer;

    public function __construct(MarkdownConfig $config = new MarkdownConfig())
    {
        $this->renderer = new NativeMarkdownRenderer(new MarkdownOptions(
            tables: $config->tables,
            strikethrough: $config->strikethrough,
            tasklists: $config->tasklists,
            urlAutolinks: $config->urlAutolinks,
            emailAutolinks: $config->emailAutolinks,
            wwwAutolinks: $config->wwwAutolinks,
            collapseWhitespace: $config->collapseWhitespace,
            latexMath: $config->latexMath,
            wikilinks: $config->wikilinks,
            underline: $config->underline,
            htmlBlocks: !$config->noHtmlBlocks,
            htmlSpans: !$config->noHtmlSpans,
            permissiveAtxHeaders: $config->permissiveAtxHeaders,
            noIndentedCodeBlocks: $config->noIndentedCodeBlocks,
            hardSoftBreaks: $config->hardSoftBreaks,
            admonitions: false,
            footnotes: false,
        ));
    }

    public function render(string $markdown): string
    {
        return $this->renderer->render($markdown);
    }
}
