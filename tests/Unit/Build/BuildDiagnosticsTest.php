<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\BuildDiagnostics;
use App\Content\Model\Author;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertContains;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEmpty;

final class BuildDiagnosticsTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-diag-test-' . uniqid();
        mkdir($this->contentDir . '/blog/assets', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    public function testWarnsOnBrokenInternalLink(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\n---\n\nSee [other](./missing.md) for details.\n");

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        $warnings = $diagnostics->warnings();
        assertCount(1, $warnings);
        assertContains('blog/post.md: broken link to "./missing.md"', $warnings);
    }

    public function testNoWarningForValidInternalLink(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\n---\n\nSee [other](./other.md) for details.\n");

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics([
            'blog/post.md' => '/blog/post/',
            'blog/other.md' => '/blog/other/',
        ]);
        $diagnostics->check($entry);

        assertEmpty($diagnostics->warnings());
    }

    public function testWarnsOnMissingImage(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\n---\n\n![Banner](/blog/assets/missing.png)\n");

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        $warnings = $diagnostics->warnings();
        assertCount(1, $warnings);
        assertContains('blog/post.md: broken image "/blog/assets/missing.png"', $warnings);
    }

    public function testNoWarningForExistingImage(): void
    {
        file_put_contents($this->contentDir . '/blog/assets/banner.svg', '<svg/>');
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\n---\n\n![Banner](/blog/assets/banner.svg)\n");

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        assertEmpty($diagnostics->warnings());
    }

    public function testNoWarningForLinksInsideCodeBlocks(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        $content = "---\ntitle: Post\n---\n\n"
            . "````markdown\n"
            . "Check out [other](./missing.md) and ![img](/blog/assets/missing.png)\n"
            . "````\n\n"
            . "Also `[inline](./gone.md)` code.\n";
        file_put_contents($entryFile, $content);

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        assertEmpty($diagnostics->warnings());
    }

    public function testNoWarningForExternalImage(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\n---\n\n![Logo](https://example.com/logo.png)\n");

        $entry = $this->createEntry($entryFile, 'blog', 'post');
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        assertEmpty($diagnostics->warnings());
    }

    public function testWarnsOnUnknownAuthor(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\nauthors:\n  - ghost\n---\n\nHello.\n");

        $entry = $this->createEntry(
            filePath: $entryFile,
            collection: 'blog',
            slug: 'post',
            authors: ['ghost'],
        );
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        $warnings = $diagnostics->warnings();
        assertCount(1, $warnings);
        assertContains('blog/post.md: unknown author "ghost"', $warnings);
    }

    public function testNoWarningForKnownAuthor(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\nauthors:\n  - john-doe\n---\n\nHello.\n");

        $entry = $this->createEntry(
            filePath: $entryFile,
            collection: 'blog',
            slug: 'post',
            authors: ['john-doe'],
        );
        $authors = ['john-doe' => new Author(slug: 'john-doe', title: 'John Doe', email: '', url: '', avatar: '', bodyOffset: 0, bodyLength: 0, filePath: '')];
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/'], $authors);
        $diagnostics->check($entry);

        assertEmpty($diagnostics->warnings());
    }

    public function testWarnsOnEmptyTag(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Post\ntags:\n  - ''\n---\n\nHello.\n");

        $entry = $this->createEntry(
            filePath: $entryFile,
            collection: 'blog',
            slug: 'post',
            tags: [''],
        );
        $diagnostics = $this->createDiagnostics(['blog/post.md' => '/blog/post/']);
        $diagnostics->check($entry);

        $warnings = $diagnostics->warnings();
        assertCount(1, $warnings);
        assertContains('blog/post.md: empty tag value', $warnings);
    }

    /**
     * @param array<string, string> $fileToPermalink
     * @param array<string, Author> $authors
     */
    private function createDiagnostics(array $fileToPermalink = [], array $authors = []): BuildDiagnostics
    {
        $siteConfig = new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: 'https://example.com',
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: ['tags', 'categories'],
            params: [],
        );

        return new BuildDiagnostics($this->contentDir, $fileToPermalink, $siteConfig, $authors);
    }

    /**
     * @param list<string> $tags
     * @param list<string> $categories
     * @param list<string> $authors
     */
    private function createEntry(
        string $filePath,
        string $collection = 'blog',
        string $slug = 'post',
        string $title = 'Post',
        array $tags = [],
        array $categories = [],
        array $authors = [],
    ): Entry {
        $content = file_get_contents($filePath);
        $bodyMarker = "---\n\n";
        $bodyPos = strpos($content, $bodyMarker, 4);
        $bodyOffset = $bodyPos !== false ? $bodyPos + strlen($bodyMarker) : 0;
        $bodyLength = strlen($content) - $bodyOffset;

        return new Entry(
            filePath: $filePath,
            collection: $collection,
            slug: $slug,
            title: $title,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: $tags,
            categories: $categories,
            authors: $authors,
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: $bodyOffset,
            bodyLength: $bodyLength,
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
