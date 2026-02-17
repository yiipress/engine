<?php

declare(strict_types=1);

namespace App\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertDirectoryExists;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertLessThan;
use function PHPUnit\Framework\assertNotFalse;
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

        $manifestPath = $this->manifestPath();
        if (is_file($manifestPath)) {
            unlink($manifestPath);
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

    public function testFeedEntriesAreSortedChronologically(): void
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

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        $secondPostPos = strpos($atom, '<title>Second Post</title>');
        $testPostPos = strpos($atom, '<title>Test Post</title>');

        assertNotFalse($secondPostPos);
        assertNotFalse($testPostPos);
        assertLessThan($testPostPos, $secondPostPos, 'Second Post (2024-05-20) should appear before Test Post (2024-03-15) in desc order');
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

    public function testBuildExcludesFutureDatedEntriesByDefault(): void
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

        $futureFile = $this->outputDir . '/blog/future-post/index.html';
        assertFalse(is_file($futureFile), 'Future-dated entry should not be built by default');

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('future-post', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringNotContainsString('Future Post', $atom);
    }

    public function testBuildIncludesFutureEntriesWithFlag(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --future'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode);

        $futureFile = $this->outputDir . '/blog/future-post/index.html';
        assertFileExists($futureFile);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('future-post', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringContainsString('Future Post', $atom);
    }

    public function testBuildGeneratesPaginatedListingPages(): void
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
        assertStringContainsString('Listing pages:', $outputText);

        $listingFile = $this->outputDir . '/blog/index.html';
        assertFileExists($listingFile);

        $html = file_get_contents($listingFile);
        assertStringContainsString('Blog', $html);
        assertStringContainsString('Test Post', $html);
        assertStringContainsString('Second Post', $html);
    }

    public function testBuildGeneratesTaxonomyPages(): void
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
        assertStringContainsString('Taxonomy pages:', $outputText);

        $tagsIndex = $this->outputDir . '/tags/index.html';
        assertFileExists($tagsIndex);
        $tagsHtml = file_get_contents($tagsIndex);
        assertStringContainsString('Tags', $tagsHtml);
        assertStringContainsString('php', $tagsHtml);

        $phpTagPage = $this->outputDir . '/tags/php/index.html';
        assertFileExists($phpTagPage);
        $phpHtml = file_get_contents($phpTagPage);
        assertStringContainsString('php', $phpHtml);
        assertStringContainsString('Test Post', $phpHtml);

        $categoriesIndex = $this->outputDir . '/categories/index.html';
        assertFileExists($categoriesIndex);
    }

    public function testBuildRespectsExplicitCollectionOrder(): void
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

        $listingHtml = file_get_contents($this->outputDir . '/page/index.html');
        $faqPos = strpos($listingHtml, 'FAQ');
        $aboutPos = strpos($listingHtml, 'About');
        assertNotFalse($faqPos);
        assertNotFalse($aboutPos);
        assertLessThan($aboutPos, $faqPos, 'FAQ should appear before About due to explicit order');
    }

    public function testBuildResolvesMarkdownFileLinks(): void
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

        $html = file_get_contents($this->outputDir . '/blog/second-post/index.html');
        assertStringContainsString('href="/blog/test-post/"', $html);
        assertStringContainsString('href="/contact/"', $html);
        assertStringNotContainsString('.md', $html);
    }

    public function testBuildGeneratesDateArchivePages(): void
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
        assertStringContainsString('Archive pages:', $outputText);

        $yearlyPage = $this->outputDir . '/blog/2024/index.html';
        assertFileExists($yearlyPage);
        $yearlyHtml = file_get_contents($yearlyPage);
        assertStringContainsString('Blog', $yearlyHtml);
        assertStringContainsString('2024', $yearlyHtml);
        assertStringContainsString('Test Post', $yearlyHtml);
        assertStringContainsString('Second Post', $yearlyHtml);

        $monthlyPage = $this->outputDir . '/blog/2024/03/index.html';
        assertFileExists($monthlyPage);
        $monthlyHtml = file_get_contents($monthlyPage);
        assertStringContainsString('March', $monthlyHtml);
        assertStringContainsString('2024', $monthlyHtml);
        assertStringContainsString('Test Post', $monthlyHtml);

        $monthlyPage2 = $this->outputDir . '/blog/2024/05/index.html';
        assertFileExists($monthlyPage2);
        $monthlyHtml2 = file_get_contents($monthlyPage2);
        assertStringContainsString('May', $monthlyHtml2);
        assertStringContainsString('Second Post', $monthlyHtml2);
    }

    public function testBuildGeneratesAuthorPages(): void
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
        assertStringContainsString('Author pages:', $outputText);

        $authorsIndex = $this->outputDir . '/authors/index.html';
        assertFileExists($authorsIndex);
        $indexHtml = file_get_contents($authorsIndex);
        assertStringContainsString('Authors', $indexHtml);
        assertStringContainsString('John Doe', $indexHtml);
        assertStringContainsString('/authors/john-doe/', $indexHtml);

        $authorPage = $this->outputDir . '/authors/john-doe/index.html';
        assertFileExists($authorPage);
        $authorHtml = file_get_contents($authorPage);
        assertStringContainsString('John Doe', $authorHtml);
        assertStringContainsString('john@example.com', $authorHtml);
        assertStringContainsString('PHP developer', $authorHtml);
        assertStringContainsString('Test Post', $authorHtml);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('/authors/', $sitemap);
        assertStringContainsString('/authors/john-doe/', $sitemap);
    }

    public function testBuildRendersNavigationInOutput(): void
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

        $entryHtml = file_get_contents($this->outputDir . '/blog/second-post/index.html');
        assertStringContainsString('<nav>', $entryHtml);
        assertStringContainsString('Home', $entryHtml);
        assertStringContainsString('Blog', $entryHtml);
        assertStringContainsString('href="/"', $entryHtml);
        assertStringContainsString('href="/blog/"', $entryHtml);

        assertStringContainsString('<footer>', $entryHtml);
        assertStringContainsString('Privacy', $entryHtml);

        $listingHtml = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('Home', $listingHtml);
        assertStringContainsString('href="/blog/"', $listingHtml);

        $tagsHtml = file_get_contents($this->outputDir . '/tags/index.html');
        assertStringContainsString('Home', $tagsHtml);

        $contactHtml = file_get_contents($this->outputDir . '/contact/index.html');
        assertStringContainsString('Home', $contactHtml);
    }

    public function testBuildRendersStandalonePages(): void
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
        assertStringContainsString('Standalone pages:', $outputText);

        $contactFile = $this->outputDir . '/contact/index.html';
        assertFileExists($contactFile);

        $html = file_get_contents($contactFile);
        assertStringContainsString('Contact', $html);
        assertStringContainsString('Get in touch', $html);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringContainsString('/contact/', $sitemap);
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

    public function testDryRunListsFilesWithoutWriting(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --dry-run'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Dry run failed: $outputText");
        assertStringContainsString('Dry run', $outputText);
        assertStringContainsString('index.html', $outputText);
        assertStringContainsString('sitemap.xml', $outputText);
        assertStringContainsString('Total:', $outputText);
        assertFalse(is_dir($this->outputDir));
    }

    public function testIncrementalBuildSkipsUnchangedEntries(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';
        $manifestPath = $this->manifestPath();

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $firstOutput,
            $firstExitCode,
        );

        $firstOutputText = implode("\n", $firstOutput);
        assertSame(0, $firstExitCode, "First build failed: $firstOutputText");
        assertStringContainsString('Build complete', $firstOutputText);

        $secondOutput = [];
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $secondOutput,
            $secondExitCode,
        );

        $secondOutputText = implode("\n", $secondOutput);
        assertSame(0, $secondExitCode, "Second build failed: $secondOutputText");
        assertStringContainsString('No changes detected', $secondOutputText);

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
    }

    public function testIncrementalBuildRebuildsChangedEntry(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';
        $manifestPath = $this->manifestPath();

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $firstOutput,
            $firstExitCode,
        );

        assertSame(0, $firstExitCode, 'First build failed: ' . implode("\n", $firstOutput));

        $entryFile = $contentDir . '/blog/2024-03-15-test-post.md';
        $originalContent = file_get_contents($entryFile);
        assertNotFalse($originalContent);

        file_put_contents($entryFile, $originalContent . "\n<!-- touched -->");

        try {
            $secondOutput = [];
            exec(
                $yii . ' build'
                . ' --content-dir=' . escapeshellarg($contentDir)
                . ' --output-dir=' . escapeshellarg($this->outputDir)
                . ' 2>&1',
                $secondOutput,
                $secondExitCode,
            );

            $secondOutputText = implode("\n", $secondOutput);
            assertSame(0, $secondExitCode, "Incremental build failed: $secondOutputText");
            assertStringContainsString('Incremental build', $secondOutputText);
        } finally {
            file_put_contents($entryFile, $originalContent);
            if (is_file($manifestPath)) {
                unlink($manifestPath);
            }
        }
    }

    private function manifestPath(): string
    {
        return dirname(__DIR__, 3) . '/runtime/cache/build-manifest-' . hash('xxh128', $this->outputDir) . '.json';
    }
}
