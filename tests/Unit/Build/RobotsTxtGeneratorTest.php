<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\RobotsTxtGenerator;
use App\Content\Model\MarkdownConfig;
use App\Content\Model\RobotsTxtConfig;
use App\Content\Model\RobotsTxtRule;
use App\Content\Model\SiteConfig;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class RobotsTxtGeneratorTest extends TestCase
{
    public function testGeneratesDefaultPermissiveRobotsTxt(): void
    {
        $siteConfig = $this->createSiteConfig();
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('User-agent: *', $result);
        assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $result);
    }

    public function testDefaultHasNoDisallowDirectives(): void
    {
        $siteConfig = $this->createSiteConfig();
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringNotContainsString('Disallow:', $result);
    }

    public function testReturnsEmptyWhenGenerateIsFalse(): void
    {
        $siteConfig = $this->createSiteConfig(robotsTxt: new RobotsTxtConfig(generate: false));
        $generator = new RobotsTxtGenerator();

        assertSame('', $generator->generate($siteConfig));
    }

    public function testRendersDisallowRules(): void
    {
        $siteConfig = $this->createSiteConfig(robotsTxt: new RobotsTxtConfig(rules: [
            new RobotsTxtRule(userAgent: '*', disallow: ['/private/', '/admin/']),
        ]));
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('User-agent: *', $result);
        assertStringContainsString('Disallow: /private/', $result);
        assertStringContainsString('Disallow: /admin/', $result);
    }

    public function testRendersAllowRules(): void
    {
        $siteConfig = $this->createSiteConfig(robotsTxt: new RobotsTxtConfig(rules: [
            new RobotsTxtRule(userAgent: '*', allow: ['/public/'], disallow: ['/']),
        ]));
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('Allow: /public/', $result);
        assertStringContainsString('Disallow: /', $result);
    }

    public function testRendersCrawlDelay(): void
    {
        $siteConfig = $this->createSiteConfig(robotsTxt: new RobotsTxtConfig(rules: [
            new RobotsTxtRule(userAgent: 'Googlebot', crawlDelay: 10),
        ]));
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('User-agent: Googlebot', $result);
        assertStringContainsString('Crawl-delay: 10', $result);
    }

    public function testMultipleUserAgentRules(): void
    {
        $siteConfig = $this->createSiteConfig(robotsTxt: new RobotsTxtConfig(rules: [
            new RobotsTxtRule(userAgent: 'Googlebot', disallow: []),
            new RobotsTxtRule(userAgent: 'Bingbot', disallow: ['/tmp/']),
        ]));
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('User-agent: Googlebot', $result);
        assertStringContainsString('User-agent: Bingbot', $result);
        assertStringContainsString('Disallow: /tmp/', $result);
    }

    public function testNoSitemapWhenBaseUrlEmpty(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: '');
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringNotContainsString('Sitemap:', $result);
    }

    public function testSitemapUsesTrailingSlashNormalization(): void
    {
        $siteConfig = $this->createSiteConfig(baseUrl: 'https://example.com/');
        $generator = new RobotsTxtGenerator();

        $result = $generator->generate($siteConfig);

        assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $result);
    }

    private function createSiteConfig(
        string $baseUrl = 'https://example.com',
        RobotsTxtConfig $robotsTxt = new RobotsTxtConfig(),
    ): SiteConfig {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: $baseUrl,
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            markdown: new MarkdownConfig(),
            robotsTxt: $robotsTxt,
        );
    }
}
