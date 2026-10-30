<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\MarkdownConfig;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;

use function PHPUnit\Framework\assertSame;

final class MarkdownProcessorTest extends TestCase
{
    public function testUsesMarkdownOptionsFromSiteConfig(): void
    {
        $processor = new MarkdownProcessor(new MarkdownRenderer());

        $processor->applySiteConfig($this->createSiteConfig(new MarkdownConfig(hardSoftBreaks: false)));

        assertSame("<p>first\nsecond</p>\n", $processor->process("first\nsecond\n", $this->createEntry()));
    }

    private function createSiteConfig(MarkdownConfig $markdown): SiteConfig
    {
        return new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: '',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            markdown: $markdown,
        );
    }

    private function createEntry(): Entry
    {
        return new Entry(
            filePath: __FILE__,
            collection: 'blog',
            slug: 'test',
            title: 'Test',
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
    }
}
