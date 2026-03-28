<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\RedirectPageWriter;
use App\Content\Model\Entry;
use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

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
