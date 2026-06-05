<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Build\RedirectPageWriter;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\I18n\UiText;
use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use SplFileInfo;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertStringContainsString;

final class RedirectPageWriterTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-redirect-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->outputDir);
    }

    public function testWritesRedirectFile(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/new-url/');
        $filePath = $this->outputDir . '/old-url/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        assertFileExists($filePath);
    }

    public function testContainsMetaRefresh(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/new-url/');
        $filePath = $this->outputDir . '/old/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        $html = file_get_contents($filePath);
        assertStringContainsString('http-equiv="refresh"', $html);
        assertStringContainsString('url=https://example.com/new-url/', $html);
    }

    public function testContainsJsRedirect(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/new-url/');
        $filePath = $this->outputDir . '/old/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        $html = file_get_contents($filePath);
        assertStringContainsString('window.location.replace', $html);
        assertStringContainsString('https://example.com/new-url/', $html);
    }

    public function testContainsCanonicalLink(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/new-url/');
        $filePath = $this->outputDir . '/old/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        $html = file_get_contents($filePath);
        assertStringContainsString('rel="canonical"', $html);
        assertStringContainsString('href="https://example.com/new-url/"', $html);
    }

    public function testCreatesDirectories(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/dest/');
        $filePath = $this->outputDir . '/deep/nested/path/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        assertFileExists($filePath);
    }

    public function testEscapesSpecialCharsInHtmlAttributes(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/path?a=1&b=2');
        $filePath = $this->outputDir . '/old/index.html';

        new RedirectPageWriter()->write($entry, $filePath);

        $html = file_get_contents($filePath);
        // HTML attributes must use &amp;
        assertStringContainsString('href="https://example.com/path?a=1&amp;b=2"', $html);
        assertStringContainsString('url=https://example.com/path?a=1&amp;b=2', $html);
    }

    public function testLocalizesRedirectPage(): void
    {
        $entry = $this->createEntry(redirectTo: 'https://example.com/new-url/');
        $filePath = $this->outputDir . '/localized/index.html';
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        $ui = UiText::forTheme('ru', new TemplateResolver($registry), 'minimal');

        new RedirectPageWriter()->write($entry, $filePath, 'ru', $ui);

        $html = file_get_contents($filePath);
        assertStringContainsString('<html lang="ru">', $html);
        assertStringContainsString('Перенаправление...', $html);
        assertStringContainsString('Эта страница переехала.', $html);
    }

    public function testPrefixesSiteRootRedirectWithBaseUrlPath(): void
    {
        $entry = $this->createEntry(redirectTo: '/blog/');
        $filePath = $this->outputDir . '/index.html';

        new RedirectPageWriter()->write(
            $entry,
            $filePath,
            siteConfig: $this->createSiteConfig('https://samdark.github.io/blog/'),
            sourcePermalink: '/',
        );

        $html = file_get_contents($filePath);
        assertStringContainsString('href="/blog/blog/"', $html);
        assertStringContainsString('url=/blog/blog/', $html);
        assertStringContainsString('window.location.replace("\\/blog\\/blog\\/")', $html);
    }

    public function testRejectsSelfRedirectAfterBaseUrlPathResolution(): void
    {
        $entry = $this->createEntry(redirectTo: '/');
        $filePath = $this->outputDir . '/index.html';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Redirect from "/" to "/" resolves to the same public URL.');

        new RedirectPageWriter()->write(
            $entry,
            $filePath,
            siteConfig: $this->createSiteConfig('https://samdark.github.io/blog/'),
            sourcePermalink: '/',
        );
    }

    private function createEntry(string $redirectTo): Entry
    {
        $file = $this->outputDir . '/entry.md';
        file_put_contents($file, "---\ntitle: Old Post\nredirect_to: $redirectTo\n---\n");

        return new Entry(
            filePath: $file,
            collection: 'blog',
            slug: 'old-post',
            title: 'Old Post',
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
            redirectTo: $redirectTo,
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
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

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
