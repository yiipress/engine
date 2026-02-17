<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\MarkdownConfig;
use App\Content\Model\SiteConfig;
use RuntimeException;

use function file_get_contents;
use function yaml_parse;

final class SiteConfigParser
{
    public function parse(string $filePath): SiteConfig
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read file: $filePath");
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new RuntimeException("Invalid YAML in file: $filePath");
        }

        return new SiteConfig(
            title: (string) ($data['title'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            baseUrl: (string) ($data['base_url'] ?? ''),
            language: (string) ($data['language'] ?? 'en'),
            charset: (string) ($data['charset'] ?? 'UTF-8'),
            defaultAuthor: (string) ($data['default_author'] ?? ''),
            dateFormat: (string) ($data['date_format'] ?? 'Y-m-d'),
            entriesPerPage: (int) ($data['entries_per_page'] ?? 10),
            permalink: (string) ($data['permalink'] ?? '/:collection/:slug/'),
            taxonomies: isset($data['taxonomies']) && \is_array($data['taxonomies'])
                ? array_values(array_map(strval(...), $data['taxonomies']))
                : [],
            params: isset($data['params']) && \is_array($data['params'])
                ? $data['params']
                : [],
            markdown: self::parseMarkdownConfig($data['markdown'] ?? []),
            theme: (string) ($data['theme'] ?? ''),
        );
    }

    /**
     * @param mixed $data
     * @return MarkdownConfig
     */
    private static function parseMarkdownConfig(mixed $data): MarkdownConfig
    {
        if (!\is_array($data)) {
            return new MarkdownConfig();
        }

        return new MarkdownConfig(
            tables: (bool) ($data['tables'] ?? true),
            strikethrough: (bool) ($data['strikethrough'] ?? true),
            tasklists: (bool) ($data['tasklists'] ?? true),
            autolinks: (bool) ($data['autolinks'] ?? true),
            collapseWhitespace: (bool) ($data['collapse_whitespace'] ?? false),
            latexMath: (bool) ($data['latex_math'] ?? false),
            wikilinks: (bool) ($data['wikilinks'] ?? false),
            underline: (bool) ($data['underline'] ?? false),
            htmlBlocks: (bool) ($data['html_blocks'] ?? true),
            htmlSpans: (bool) ($data['html_spans'] ?? true),
        );
    }
}
