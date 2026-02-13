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
        assertTrue($config->autolinks);
        assertFalse($config->collapseWhitespace);
        assertFalse($config->latexMath);
        assertFalse($config->wikilinks);
        assertFalse($config->underline);
        assertTrue($config->htmlBlocks);
        assertTrue($config->htmlSpans);
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

            assertFalse($config->markdown->tables);
            assertFalse($config->markdown->strikethrough);
            assertTrue($config->markdown->tasklists);
            assertTrue($config->markdown->autolinks);
            assertTrue($config->markdown->latexMath);
            assertTrue($config->markdown->underline);
            assertFalse($config->markdown->htmlBlocks);
            assertTrue($config->markdown->htmlSpans);
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
