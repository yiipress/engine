<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\Model\MarkdownConfig;
use App\Content\Model\SiteConfig;
use App\Content\Parser\SiteConfigParser;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

final class MarkdownConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = new MarkdownConfig();

        assertTrue($config->tables);
        assertTrue($config->strikethrough);
        assertTrue($config->tasklists);
        assertTrue($config->urlAutolinks);
        assertTrue($config->emailAutolinks);
        assertTrue($config->wwwAutolinks);
        assertTrue($config->collapseWhitespace);
        assertFalse($config->latexMath);
        assertFalse($config->wikilinks);
        assertFalse($config->underline);
        assertTrue($config->noHtmlBlocks);
        assertTrue($config->noHtmlSpans);
        assertFalse($config->permissiveAtxHeaders);
        assertFalse($config->noIndentedCodeBlocks);
        assertTrue($config->hardSoftBreaks);
    }

    public function testSiteConfigHasDefaultMarkdownConfig(): void
    {
        $siteConfig = new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: '',
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
        );

        assertInstanceOf(MarkdownConfig::class, $siteConfig->markdown);
        assertTrue($siteConfig->markdown->tables);
    }

    public function testParserReadsMarkdownSection(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yiipress_test_');
        file_put_contents($tmpFile, <<<YAML
title: "Test"
markdown:
  tables: false
  strikethrough: false
  latex_math: true
  underline: true
  html_blocks: false
YAML);

        try {
            $parser = new SiteConfigParser();
            $config = $parser->parse($tmpFile);

            assertFalse($config->markdown->tables, 'tables should be false');
            assertFalse($config->markdown->strikethrough, 'strikethrough should be false');
            assertTrue($config->markdown->tasklists, 'tasklists should be true (default)');
            assertTrue($config->markdown->urlAutolinks, 'urlAutolinks should be true (default)');
            assertTrue($config->markdown->emailAutolinks, 'emailAutolinks should be true (default)');
            assertTrue($config->markdown->wwwAutolinks, 'wwwAutolinks should be true (default)');
            assertTrue($config->markdown->collapseWhitespace, 'collapseWhitespace should be true (default)');
            assertTrue($config->markdown->latexMath, 'latexMath should be true (set in config)');
            assertTrue($config->markdown->underline, 'underline should be true (set in config)');
            assertFalse($config->markdown->noHtmlBlocks, 'noHtmlBlocks should be false (html_blocks: false)');
            assertTrue($config->markdown->noHtmlSpans, 'noHtmlSpans should be true (default)');
            assertFalse($config->markdown->permissiveAtxHeaders, 'permissiveAtxHeaders should be false (default)');
            assertFalse($config->markdown->noIndentedCodeBlocks, 'noIndentedCodeBlocks should be false (default)');
            assertTrue($config->markdown->hardSoftBreaks, 'hardSoftBreaks should be true (default)');
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParserHandlesMissingMarkdownSection(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'yiipress_test_');
        file_put_contents($tmpFile, <<<YAML
title: "Test"
YAML);

        try {
            $parser = new SiteConfigParser();
            $config = $parser->parse($tmpFile);

            assertTrue($config->markdown->tables);
            assertTrue($config->markdown->strikethrough);
        } finally {
            unlink($tmpFile);
        }
    }
}
