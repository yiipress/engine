<?php

declare(strict_types=1);

namespace App\Content\Parser;

use App\Content\Model\MarkdownConfig;
use App\Content\Model\SiteConfig;
use RuntimeException;

use function file_get_contents;
use function is_array;
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
            taxonomies: isset($data['taxonomies']) && is_array($data['taxonomies'])
                ? array_values(array_map(strval(...), $data['taxonomies']))
                : [],
            params: isset($data['params']) && is_array($data['params'])
                ? $data['params']
                : [],
            markdown: self::parseMarkdownConfig($data['markdown'] ?? []),
            theme: (string) ($data['theme'] ?? ''),
        );
    }

    /**
     * @param mixed|array<string, bool> $data
     * @return MarkdownConfig
     */
    private static function parseMarkdownConfig(mixed $data): MarkdownConfig
    {
        if (!is_array($data)) {
            return new MarkdownConfig();
        }

        $constructorArgs = [];
        
        if (array_key_exists('tables', $data)) {
            $constructorArgs['tables'] = (bool) $data['tables'];
        }
        if (array_key_exists('strikethrough', $data)) {
            $constructorArgs['strikethrough'] = (bool) $data['strikethrough'];
        }
        if (array_key_exists('tasklists', $data)) {
            $constructorArgs['tasklists'] = (bool) $data['tasklists'];
        }
        if (array_key_exists('url_autolinks', $data)) {
            $constructorArgs['urlAutolinks'] = (bool) $data['url_autolinks'];
        }
        if (array_key_exists('email_autolinks', $data)) {
            $constructorArgs['emailAutolinks'] = (bool) $data['email_autolinks'];
        }
        if (array_key_exists('www_autolinks', $data)) {
            $constructorArgs['wwwAutolinks'] = (bool) $data['www_autolinks'];
        }
        if (array_key_exists('collapse_whitespace', $data)) {
            $constructorArgs['collapseWhitespace'] = (bool) $data['collapse_whitespace'];
        }
        if (array_key_exists('latex_math', $data)) {
            $constructorArgs['latexMath'] = (bool) $data['latex_math'];
        }
        if (array_key_exists('wikilinks', $data)) {
            $constructorArgs['wikilinks'] = (bool) $data['wikilinks'];
        }
        if (array_key_exists('underline', $data)) {
            $constructorArgs['underline'] = (bool) $data['underline'];
        }
        if (array_key_exists('html_blocks', $data)) {
            $constructorArgs['noHtmlBlocks'] = (bool) $data['html_blocks'];
        }
        if (array_key_exists('html_spans', $data)) {
            $constructorArgs['noHtmlSpans'] = (bool) $data['html_spans'];
        }
        if (array_key_exists('permissive_atx_headers', $data)) {
            $constructorArgs['permissiveAtxHeaders'] = (bool) $data['permissive_atx_headers'];
        }
        if (array_key_exists('no_indented_code_blocks', $data)) {
            $constructorArgs['noIndentedCodeBlocks'] = (bool) $data['no_indented_code_blocks'];
        }
        if (array_key_exists('hard_soft_breaks', $data)) {
            $constructorArgs['hardSoftBreaks'] = (bool) $data['hard_soft_breaks'];
        }

        return new MarkdownConfig(...$constructorArgs);
    }
}
