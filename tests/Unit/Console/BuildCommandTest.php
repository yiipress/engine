<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertDirectoryExists;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class BuildCommandTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = dirname(__DIR__, 2) . '/Support/Data/output';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->outputDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if ($item->isDir()) {
                    rmdir($item->getPathname());
                } else {
                    unlink($item->getPathname());
                }
            }
            rmdir($this->outputDir);
        }
    }

    public function testBuildGeneratesOutputFiles(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Build failed: $outputText");
        assertStringContainsString('Build complete.', $outputText);
        assertDirectoryExists($this->outputDir);
    }

    public function testBuildOutputContainsRenderedHtml(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode);

        $entryFile = $this->outputDir . '/blog/test-post/index.html';
        assertFileExists($entryFile);

        $html = file_get_contents($entryFile);
        assertStringContainsString('<!DOCTYPE html>', $html);
        assertStringContainsString('<title>Test Post', $html);
        assertStringContainsString('<p>This is the body of the test post.</p>', $html);
        assertStringContainsString('<p>It has multiple paragraphs.</p>', $html);
    }

    public function testBuildWithParallelWorkers(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --workers=2'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Parallel build failed: $outputText");
        assertStringContainsString('Build complete.', $outputText);
        assertStringContainsString('2 workers', $outputText);

        $entryFile = $this->outputDir . '/blog/test-post/index.html';
        assertFileExists($entryFile);

        $html = file_get_contents($entryFile);
        assertStringContainsString('<!DOCTYPE html>', $html);
        assertStringContainsString('<p>This is the body of the test post.</p>', $html);
    }

    public function testBuildGeneratesFeedsForCollectionsWithFeedEnabled(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Build failed: $outputText");
        assertStringContainsString('Feeds generated:', $outputText);

        $atomFile = $this->outputDir . '/blog/feed.xml';
        assertFileExists($atomFile);
        $atom = file_get_contents($atomFile);
        assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $atom);
        assertStringContainsString('<title>Blog</title>', $atom);
        assertStringContainsString('<title>Test Post</title>', $atom);
        assertStringContainsString('<content type="html">', $atom);

        $rssFile = $this->outputDir . '/blog/rss.xml';
        assertFileExists($rssFile);
        $rss = file_get_contents($rssFile);
        assertStringContainsString('<rss version="2.0"', $rss);
        assertStringContainsString('<title>Test Post</title>', $rss);
        assertStringContainsString('<content:encoded>', $rss);
    }

    public function testBuildGeneratesSitemap(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Build failed: $outputText");
        assertStringContainsString('Sitemap generated.', $outputText);

        $sitemapFile = $this->outputDir . '/sitemap.xml';
        assertFileExists($sitemapFile);

        $xml = file_get_contents($sitemapFile);
        assertStringContainsString('https://test.example.com/', $xml);
        assertStringContainsString('https://test.example.com/blog/test-post/', $xml);
    }

    public function testBuildSkipsFeedForCollectionsWithFeedDisabled(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode);

        $pageFeed = $this->outputDir . '/page/feed.xml';
        assertFalse(is_file($pageFeed), 'Feed should not be generated for collections with feed: false');
    }

    public function testBuildExcludesDraftEntriesByDefault(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode);

        $draftFile = $this->outputDir . '/blog/custom-slug/index.html';
        assertFalse(is_file($draftFile), 'Draft entry should not be built by default');

        $publishedFile = $this->outputDir . '/blog/test-post/index.html';
        assertFileExists($publishedFile);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('custom-slug', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringNotContainsString('No Date Post', $atom);
    }

    public function testBuildIncludesDraftsWithFlag(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --drafts'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode);

        $draftFile = $this->outputDir . '/blog/custom-slug/index.html';
        assertFileExists($draftFile);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('custom-slug', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringContainsString('No Date Post', $atom);
    }

    public function testBuildFailsWithMissingContentDir(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';

        exec(
            $yii . ' build'
            . ' --content-dir=/nonexistent/path'
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(65, $exitCode);
        assertStringContainsString('Content directory not found', implode("\n", $output));
    }
}
