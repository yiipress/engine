<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Content;

use YiiPress\Content\Model\SearchConfig;
use YiiPress\Content\Model\SiteConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class SiteConfigTest extends TestCase
{
    public static function searchResultsProvider(): iterable
    {
        yield 'search disabled' => [null, 10];
        yield 'configured result count' => [new SearchConfig(results: 25), 25];
    }

    #[DataProvider('searchResultsProvider')]
    public function testSearchResults(?SearchConfig $search, int $expected): void
    {
        $siteConfig = new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'utf-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:slug/',
            taxonomies: [],
            params: [],
            search: $search,
        );

        assertSame($expected, $siteConfig->searchResults());
    }
}
