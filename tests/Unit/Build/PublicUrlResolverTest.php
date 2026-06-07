<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\UrlResolver;
use YiiPress\Content\Model\SiteConfig;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class PublicUrlResolverTest extends TestCase
{
    public function testBuildsSitePathRelativeToCurrentPageRoot(): void
    {
        assertSame('../../tags/php/', UrlResolver::sitePath('/tags/php/', '../../'));
        assertSame('./tags/php/', UrlResolver::sitePath('tags/php/', './'));
        assertSame('https://example.github.io/blog/tags/php/', UrlResolver::sitePath('/tags/php/', 'https://example.github.io/blog/'));
    }

    public function testLeavesExternalAndSpecialSitePathsAlone(): void
    {
        assertSame('https://other.example/tags/php/', UrlResolver::sitePath('https://other.example/tags/php/', '../../'));
        assertSame('#section', UrlResolver::sitePath('#section', '../../'));
        assertSame('mailto:test@example.com', UrlResolver::sitePath('mailto:test@example.com', '../../'));
    }

    public function testPrefixesSiteRootUrlWithBasePath(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertSame('/blog/posts/', UrlResolver::browserUrl($siteConfig, '/posts/'));
    }

    public function testLeavesSiteRootUrlAloneWhenBaseUrlHasNoPath(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.com/');

        assertSame('/posts/', UrlResolver::browserUrl($siteConfig, '/posts/'));
    }

    public function testLeavesAbsoluteUrlAlone(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertSame('https://other.example/posts/', UrlResolver::browserUrl($siteConfig, 'https://other.example/posts/'));
    }

    public function testAbsoluteUrlPrefixesOriginAndBasePath(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertSame('https://example.github.io/blog/tags/php/', UrlResolver::absoluteUrl($siteConfig, '/tags/php/'));
        assertSame('https://example.github.io/blog/tags/php/', UrlResolver::absoluteUrl($siteConfig, 'tags/php/'));
    }

    public function testDetectsSelfRedirectAfterBasePathResolution(): void
    {
        $siteConfig = $this->createSiteConfig('https://example.github.io/blog/');

        assertTrue(UrlResolver::isSamePublicUrl($siteConfig, '/', '/'));
        assertFalse(UrlResolver::isSamePublicUrl($siteConfig, '/', '/blog/'));
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
