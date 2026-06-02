<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\PageActionUrlFormatter;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class PageActionUrlFormatterTest extends TestCase
{
    public function testFormatsEditPageUrlTemplate(): void
    {
        $entry = $this->createEntry('/site/docs/guide/intro.md', 'Intro & Setup');
        $siteConfig = $this->createSiteConfig('https://example.com/docs/');

        $url = PageActionUrlFormatter::format(
            'https://github.com/acme/site/edit/main/{path}?title={title}&url={url}&permalink={permalink}',
            $siteConfig,
            $entry,
            '/guide/intro/',
            '/site/docs',
        );

        assertSame(
            'https://github.com/acme/site/edit/main/guide/intro.md?title=Intro%20%26%20Setup&url=https%3A%2F%2Fexample.com%2Fdocs%2Fguide%2Fintro%2F&permalink=/guide/intro/',
            $url,
        );
    }

    private function createSiteConfig(string $baseUrl): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: $baseUrl,
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
        );
    }

    private function createEntry(string $filePath, string $title): Entry
    {
        return new Entry(
            filePath: $filePath,
            collection: 'docs',
            slug: 'intro',
            title: $title,
            date: new DateTimeImmutable('2026-01-01'),
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
