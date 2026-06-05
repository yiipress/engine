<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\PublicUrlResolver;
use YiiPress\Content\Model\SiteConfig;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class PublicUrlResolverTest extends TestCase
{
    public function testPrefixesSiteRootUrlWithBasePath(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertSame('/blog/posts/', PublicUrlResolver::browserUrl($siteConfig, '/posts/'));
    }

    public function testLeavesSiteRootUrlAloneWhenBaseUrlHasNoPath(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.com/');

        assertSame('/posts/', PublicUrlResolver::browserUrl($siteConfig, '/posts/'));
    }

    public function testLeavesAbsoluteUrlAlone(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertSame('https://other.example/posts/', PublicUrlResolver::browserUrl($siteConfig, 'https://other.example/posts/'));
    }

    public function testDetectsSelfRedirectAfterBasePathResolution(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertTrue(PublicUrlResolver::isSamePublicUrl($siteConfig, '/', '/'));
        assertFalse(PublicUrlResolver::isSamePublicUrl($siteConfig, '/', '/blog/'));
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
}
