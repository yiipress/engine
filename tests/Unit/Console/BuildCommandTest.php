<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Console\BuildCommand;
use YiiPress\Hook\BuildFinishedEvent;
use YiiPress\Hook\BuildStartedEvent;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Render\MarkdownRenderer;
use YiiPress\RuntimePaths;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Yii\Console\ExitCode;

use function PHPUnit\Framework\assertDirectoryExists;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertLessThan;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertMatchesRegularExpression;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class BuildCommandTest extends TestCase
{
    private string $outputDir;
    /** @var list<string> */
    private array $tempContentDirs = [];

    protected function setUp(): void
    {
        $this->outputDir = dirname(__DIR__, 2) . '/Support/Data/output';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->outputDir, FilesystemIterator::SKIP_DOTS),
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
            rmdir($this->outputDir);
        }

        foreach ($this->tempContentDirs as $tempContentDir) {
            $this->removeDir($tempContentDir);
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
        assertMatchesRegularExpression('/Build complete in \d+(?:\.\d+)?(?:ms|s)\. Peak memory: \d+(?:\.\d+)? MiB\./', $outputText);
        assertDirectoryExists($this->outputDir);
    }

    public function testBuildHooksAreDispatched(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-build-hooks-test-' . uniqid();
        $contentDir = $tempDir . '/content';
        $outputDir = $tempDir . '/output';
        mkdir($contentDir, 0o755, true);
        $this->tempContentDirs[] = $tempDir;

        file_put_contents($contentDir . '/config.yaml', "title: Hook Site\nbase_url: https://example.com\nlanguages: [en]\n");
        file_put_contents($contentDir . '/index.md', "---\ntitle: Home\n---\n\nHello.\n");

        $events = [];
        $listenerCollection = (new ListenerCollection())
            ->add(static function (BuildStartedEvent $event) use (&$events): void {
                $events[] = 'build.started:' . $event->siteConfig->title;
            })
            ->add(static function (BuildFinishedEvent $event) use (&$events): void {
                $events[] = 'build.finished:' . $event->context->outputDir;
            });
        $eventDispatcher = new Dispatcher(new Provider($listenerCollection));

        $themeRegistry = new ThemeRegistry();
        $themeRegistry->register(new Theme('minimal', dirname(__DIR__, 3) . '/themes/minimal'));
        $templateResolver = new TemplateResolver($themeRegistry);
        $pipeline = new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer()));
        $command = new BuildCommand(
            rootPath: dirname(__DIR__, 3),
            contentPipeline: $pipeline,
            feedPipeline: new ContentProcessorPipeline(new MarkdownProcessor(new MarkdownRenderer())),
            themeRegistry: $themeRegistry,
            templateResolver: $templateResolver,
            eventDispatcher: $eventDispatcher,
        );
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--content-dir' => $contentDir,
            '--output-dir' => $outputDir,
            '--workers' => '1',
            '--no-cache' => true,
        ]);

        assertSame(0, $exitCode, $tester->getDisplay());
        assertSame(['build.started:Hook Site', 'build.finished:' . $outputDir], $events);
        assertFileExists($outputDir . '/index/index.html');
    }

    public function testBuildReportsInvalidSiteConfigWithoutTrace(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-build-invalid-config-test-' . uniqid();
        $contentDir = $tempDir . '/content';
        $outputDir = $tempDir . '/output';
        mkdir($contentDir, 0o755, true);
        $this->tempContentDirs[] = $tempDir;

        file_put_contents($contentDir . '/config.yaml', "title: Broken Site\n");
        file_put_contents($contentDir . '/index.md', "---\ntitle: Home\n---\n\nHello.\n");

        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(ExitCode::DATAERR, $exitCode, $outputText);
        assertStringContainsString('Invalid content configuration', $outputText);
        assertStringContainsString('Problem: The "languages" option in site configuration must be a non-empty list of language codes.', $outputText);
        assertStringContainsString('languages: [en]', $outputText);
        assertStringContainsString('If you currently have i18n.languages, move it to the top-level languages option.', $outputText);
        assertStringNotContainsString('Stack trace:', $outputText);
        assertStringNotContainsString('RuntimeException:', $outputText);
        assertStringNotContainsString('#0 ', $outputText);
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
        assertMatchesRegularExpression('/Build complete in \d+(?:\.\d+)?(?:ms|s)\. Peak memory: \d+(?:\.\d+)? MiB\./', $outputText);
        assertStringContainsString('2 workers', $outputText);

        $entryFile = $this->outputDir . '/blog/test-post/index.html';
        assertFileExists($entryFile);

        $html = file_get_contents($entryFile);
        assertStringContainsString('<!DOCTYPE html>', $html);
        assertStringContainsString('<p>This is the body of the test post.</p>', $html);
    }

    public function testBuildWithAutoWorkers(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --workers=auto'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Auto worker build failed: $outputText");
        assertMatchesRegularExpression('/Build complete in \d+(?:\.\d+)?(?:ms|s)\. Peak memory: \d+(?:\.\d+)? MiB\./', $outputText);
        assertStringContainsString('Entries written:', $outputText);
        assertMatchesRegularExpression('/Entries written: .* using \d+ workers? \(auto\)/', $outputText);
        assertFileExists($this->outputDir . '/blog/test-post/index.html');
    }

    public function testBuildNoWriteRendersWithoutCreatingOutputDirectory(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --workers=1'
            . ' --no-cache'
            . ' --no-write'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "No-write build failed: $outputText");
        assertStringContainsString('Rendering without writing output', $outputText);
        assertStringContainsString('Entries rendered:', $outputText);
        assertStringContainsString('Feeds generated:', $outputText);
        assertStringContainsString('Sitemap generated.', $outputText);
        assertFalse(is_dir($this->outputDir), 'No-write build must not create the output directory.');
    }

    public function testBuildProfilePrintsPhaseTimings(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --workers=1'
            . ' --no-cache'
            . ' --profile'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        $outputText = implode("\n", $output);

        assertSame(0, $exitCode, "Profiled build failed: $outputText");
        assertStringContainsString('Build profile:', $outputText);
        assertMatchesRegularExpression('/prepare: .*\\(\\d+(?:\\.\\d+)?%\\)/', $outputText);
        assertMatchesRegularExpression('/parse content: .*\\(\\d+(?:\\.\\d+)?%\\)/', $outputText);
        assertMatchesRegularExpression('/write entries: .*\\(\\d+(?:\\.\\d+)?%\\)/', $outputText);
        assertMatchesRegularExpression('/Build complete in \\d+(?:\\.\\d+)?(?:ms|s)\\. Peak memory: \\d+(?:\\.\\d+)? MiB\\./', $outputText);
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

        // Draft entry with redirect_to generates a redirect page, not a normal entry
        $draftFile = $this->outputDir . '/blog/custom-slug/index.html';
        assertFileExists($draftFile);
        $html = file_get_contents($draftFile);
        assertStringContainsString('http-equiv="refresh"', $html);

        // Redirect entries are excluded from sitemap and feeds
        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('custom-slug', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringNotContainsString('No Date Post', $atom);
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
        assertStringContainsString('href="../../blog/test-post/"', $html);
        assertStringContainsString('href="../../contact/"', $html);
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
        assertStringContainsString('data-ui-month="05"', $yearlyHtml);
        assertStringContainsString('data-ui-month="03"', $yearlyHtml);

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

    public function testBuildCanDisableAuthorPages(): void
    {
        $contentDir = $this->copyContentFixture();
        file_put_contents($contentDir . '/config.yaml', "\nauthor_pages: false\n", FILE_APPEND);

        $this->runBuild($contentDir);

        assertFalse(is_dir($this->outputDir . '/authors'));

        $entryHtml = file_get_contents($this->outputDir . '/blog/test-post/index.html');
        assertStringContainsString('class="author"', $entryHtml);
        assertStringContainsString('John Doe', $entryHtml);
        assertStringNotContainsString('href="../../authors/john-doe/"', $entryHtml);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('/authors/', $sitemap);
        assertStringNotContainsString('/authors/john-doe/', $sitemap);
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
        assertStringContainsString('Test Site', $entryHtml);
        assertStringContainsString('Blog', $entryHtml);
        assertStringContainsString('href="../../"', $entryHtml);
        assertStringContainsString('href="../../blog/"', $entryHtml);
        assertStringNotContainsString('class="docs-layout', $entryHtml);

        assertStringContainsString('<footer', $entryHtml);
        assertStringContainsString('Privacy', $entryHtml);

        $listingHtml = file_get_contents($this->outputDir . '/blog/index.html');
        assertStringContainsString('Test Site', $listingHtml);
        assertStringContainsString('href="../blog/"', $listingHtml);

        $tagsHtml = file_get_contents($this->outputDir . '/tags/index.html');
        assertStringContainsString('Test Site', $tagsHtml);

        $contactHtml = file_get_contents($this->outputDir . '/contact/index.html');
        assertStringContainsString('Test Site', $contactHtml);

        $aboutHtml = file_get_contents($this->outputDir . '/about/index.html');
        assertStringContainsString('class="docs-layout docs-layout-with-toc"', $aboutHtml);
        assertStringContainsString('class="docs-sidebar"', $aboutHtml);
        assertStringContainsString('class="toc-sidebar toc-sidebar-right"', $aboutHtml);
        assertStringContainsString('aria-current="page">About</a>', $aboutHtml);
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

    public function testIncrementalBuildRecreatesMissingOutputFiles(): void
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

        $entryFile = $this->outputDir . '/blog/test-post/index.html';
        assertFileExists($entryFile);
        unlink($entryFile);
        assertFalse(is_file($entryFile));

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
        assertStringContainsString('Incremental build', $secondOutputText);
        assertFileExists($entryFile);

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

    public function testConfigChangeInvalidatesCachedEntryHtml(): void
    {
        $contentDir = $this->copyContentFixture();
        file_put_contents(
            $contentDir . '/templates/entry.php',
            <<<'PHP'
<?php
declare(strict_types=1);
?>
<html><head><title><?= $h($entryTitle) ?> — <?= $h($siteTitle) ?></title></head><body><?= $content ?></body></html>
PHP,
        );

        $this->runBuild($contentDir);
        $html = file_get_contents($this->outputDir . '/blog/test-post/index.html');
        assertNotFalse($html);
        assertStringContainsString('Test Post — Test Site', $html);

        $config = file_get_contents($contentDir . '/config.yaml');
        assertNotFalse($config);
        file_put_contents($contentDir . '/config.yaml', str_replace('title: "Test Site"', 'title: "Changed Site"', $config));

        $this->runBuild($contentDir);
        $html = file_get_contents($this->outputDir . '/blog/test-post/index.html');
        assertNotFalse($html);
        assertStringContainsString('Test Post — Changed Site', $html);
        assertStringNotContainsString('Test Post — Test Site', $html);
    }

    public function testAssetChangeRewritesFingerprintedEntryHtml(): void
    {
        $contentDir = $this->copyContentFixture();
        mkdir($contentDir . '/assets', 0o755, true);
        file_put_contents($contentDir . '/assets/site.css', 'body{color:red}');
        file_put_contents(
            $contentDir . '/templates/entry.php',
            <<<'PHP'
<?php
declare(strict_types=1);
?>
<html><head><link rel="stylesheet" href="/assets/site.css"></head><body><?= $content ?></body></html>
PHP,
        );

        $this->runBuild($contentDir);
        $html = file_get_contents($this->outputDir . '/blog/test-post/index.html');
        assertNotFalse($html);
        assertMatchesRegularExpression('~/assets/site\.[a-f0-9]{12}\.css~', $html);
        preg_match('~/assets/site\.([a-f0-9]{12})\.css~', $html, $firstMatches);
        $firstHash = $firstMatches[1] ?? '';
        assertNotFalse($firstHash !== '');

        file_put_contents($contentDir . '/assets/site.css', 'body{color:blue}');

        $this->runBuild($contentDir);
        $html = file_get_contents($this->outputDir . '/blog/test-post/index.html');
        assertNotFalse($html);
        preg_match('~/assets/site\.([a-f0-9]{12})\.css~', $html, $secondMatches);
        $secondHash = $secondMatches[1] ?? '';

        assertNotFalse($secondHash !== '');
        assertStringNotContainsString('/assets/site.' . $firstHash . '.css', $html);
        assertFileExists($this->outputDir . '/assets/site.' . $secondHash . '.css');
        assertFalse(is_file($this->outputDir . '/assets/site.' . $firstHash . '.css'));
    }

    public function testNonFingerprintedAssetChangeStaysIncremental(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = $this->copyContentFixture();
        mkdir($contentDir . '/assets', 0o755, true);
        file_put_contents($contentDir . '/assets/site.css', 'body{color:red}');
        file_put_contents($contentDir . '/config.yaml', file_get_contents($contentDir . '/config.yaml') . "\nassets:\n  fingerprint: false\n");

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' 2>&1',
            $firstOutput,
            $firstExitCode,
        );

        assertSame(0, $firstExitCode, 'First build failed: ' . implode("\n", $firstOutput));
        assertFileExists($this->outputDir . '/assets/site.css');

        file_put_contents($contentDir . '/assets/site.css', 'body{color:blue}');

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
        assertStringContainsString('Incremental build', $secondOutputText);
        assertStringNotContainsString('Full rebuild', $secondOutputText);
        assertStringContainsString('body{color:blue}', (string) file_get_contents($this->outputDir . '/assets/site.css'));
    }

    public function testI18nEntryPermalinksAreConsistentAcrossGeneratedIndexes(): void
    {
        $contentDir = $this->copyContentFixture();
        $config = file_get_contents($contentDir . '/config.yaml');
        assertNotFalse($config);
        $config = str_replace('languages: ["en"]', 'languages: ["en", "ru"]', $config);
        $config .= "\ni18n:\n  languages: [\"en\", \"ru\"]\n  default_language: \"en\"\n";
        file_put_contents($contentDir . '/config.yaml', $config);

        $entryPath = $contentDir . '/blog/2024-03-15-test-post.md';
        $entry = file_get_contents($entryPath);
        assertNotFalse($entry);
        file_put_contents($entryPath, str_replace("title: \"Test Post\"\n", "title: \"Test Post\"\nlanguage: \"ru\"\n", $entry));

        $this->runBuild($contentDir, '--no-cache');

        assertFileExists($this->outputDir . '/ru/blog/test-post/index.html');
        $listing = file_get_contents($this->outputDir . '/blog/index.html');
        assertNotFalse($listing);
        assertStringContainsString('../ru/blog/test-post/', $listing);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertNotFalse($atom);
        assertStringContainsString('https://test.example.com/ru/blog/test-post/', $atom);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertNotFalse($sitemap);
        assertStringContainsString('https://test.example.com/ru/blog/test-post/', $sitemap);
    }

    public function testBuildReportsInvalidEntryDateWithFilePath(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\ndate: not-a-real-date\n---\n\nHello.\n",
        ]);

        $result = $this->runBuildResult($contentDir);

        assertSame(65, $result['exitCode'], $result['output']);
        assertStringContainsString('Invalid content configuration', $result['output']);
        assertStringContainsString('File:', $result['output']);
        assertStringContainsString($contentDir . '/index.md', $result['output']);
        assertStringContainsString('Invalid date in front matter', $result['output']);
    }

    public function testBuildRejectsDuplicatePermalinks(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\npermalink: /same/\n---\n\nHello.\n",
            'about.md' => "---\ntitle: About\npermalink: /same/\n---\n\nAbout.\n",
        ]);

        $result = $this->runBuildResult($contentDir);

        assertSame(65, $result['exitCode'], $result['output']);
        assertStringContainsString('Duplicate permalink "/same/"', $result['output']);
    }

    public function testBuildRejectsTraversingPermalink(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\npermalink: /../../outside/\n---\n\nHello.\n",
        ]);

        $result = $this->runBuildResult($contentDir);

        assertSame(65, $result['exitCode'], $result['output']);
        assertStringContainsString('Invalid permalink "/../../outside/"', $result['output']);
        assertFalse(is_file(dirname($this->outputDir) . '/outside/index.html'));
    }

    public function testBuildRejectsPermalinkWithoutTrailingSlash(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\npermalink: /about\n---\n\nHello.\n",
        ]);

        $result = $this->runBuildResult($contentDir);

        assertSame(65, $result['exitCode'], $result['output']);
        assertStringContainsString('Invalid permalink "/about"', $result['output']);
        assertFalse(is_file($this->outputDir . '/aboutindex.html'));
    }

    public function testFailedNoCacheBuildRemovesTemporaryOutputDirectory(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\npermalink: /broken\n---\n\nHello.\n",
        ]);
        mkdir($this->outputDir, 0o755, true);
        file_put_contents($this->outputDir . '/.yiipress-build', "YiiPress build output\n");
        file_put_contents($this->outputDir . '/existing.txt', 'keep');
        $tempPattern = dirname($this->outputDir) . '/.' . basename($this->outputDir) . '.tmp-*';

        foreach (glob($tempPattern) ?: [] as $tempDir) {
            $this->removeDir($tempDir);
        }

        $result = $this->runBuildResult($contentDir, '--no-cache');

        assertSame(65, $result['exitCode'], $result['output']);
        assertSame([], glob($tempPattern) ?: []);
        assertStringContainsString('keep', (string) file_get_contents($this->outputDir . '/existing.txt'));
    }

    public function testNoCacheBuildRefusesToReplaceUnmarkedOutputDirectory(): void
    {
        $contentDir = $this->createMinimalContent([
            'index.md' => "---\ntitle: Home\n---\n\nHello.\n",
        ]);
        mkdir($this->outputDir, 0o755, true);
        file_put_contents($this->outputDir . '/existing.txt', 'keep');

        $result = $this->runBuildResult($contentDir, '--no-cache');

        assertSame(1, $result['exitCode'], $result['output']);
        assertStringContainsString('Refusing to replace output directory', $result['output']);
        assertStringContainsString('keep', (string) file_get_contents($this->outputDir . '/existing.txt'));
    }

    public function testBuildUsesCustomLayoutFromFrontMatter(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));

        $entryFile = $this->outputDir . '/blog/layout-test/index.html';
        assertFileExists($entryFile);

        $html = file_get_contents($entryFile);
        assertNotFalse($html);
        assertStringContainsString('minimal-layout', $html);
        assertStringContainsString('<h1>Layout Test</h1>', $html);
        assertStringContainsString('This entry uses a custom layout.', $html);
    }

    public function testBuildWithNoCacheDoesNotCreateManifest(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';
        $manifestPath = $this->manifestPath();

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));
        assertFalse(is_file($manifestPath));
    }

    public function testBuildWritesRedirectHtmlForRedirectEntries(): void
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        $contentDir = dirname(__DIR__, 2) . '/Support/Data/content';

        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));

        $redirectFile = $this->outputDir . '/blog/old-url-post/index.html';
        assertFileExists($redirectFile);

        $html = file_get_contents($redirectFile);
        assertNotFalse($html);
        assertStringContainsString('http-equiv="refresh"', $html);
        assertStringContainsString('https://example.com/new-location/', $html);
        assertStringContainsString('window.location.replace', $html);

        $sitemap = file_get_contents($this->outputDir . '/sitemap.xml');
        assertStringNotContainsString('old-url-post', $sitemap);

        $atom = file_get_contents($this->outputDir . '/blog/feed.xml');
        assertStringNotContainsString('Old URL Post', $atom);
    }

    public function testBuildPrefixesRedirectTargetWithBaseUrlPath(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-build-project-redirect-test-' . uniqid();
        $contentDir = $tempDir . '/content';
        $outputDir = $tempDir . '/output';
        mkdir($contentDir, 0o755, true);
        mkdir($contentDir . '/blog', 0o755, true);
        $this->tempContentDirs[] = $tempDir;

        file_put_contents($contentDir . '/config.yaml', "title: Project Site\nbase_url: https://samdark.github.io/blog/\nlanguages: [en]\n");
        file_put_contents($contentDir . '/blog/_collection.yaml', "title: Blog\npermalink: /blog/:slug/\n");
        file_put_contents($contentDir . '/index.md', "---\ntitle: Home\npermalink: /\nredirect_to: /blog/\n---\n");

        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));

        $html = file_get_contents($outputDir . '/index.html');
        assertNotFalse($html);
        assertStringContainsString('href="/blog/blog/"', $html);
        assertStringContainsString('url=/blog/blog/', $html);
    }

    public function testBuildRewritesRootRelativeContentImageForSubdirectoryDeployment(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-build-project-image-test-' . uniqid();
        $contentDir = $tempDir . '/content';
        $outputDir = $tempDir . '/output';
        mkdir($contentDir . '/blog/assets', 0o755, true);
        $this->tempContentDirs[] = $tempDir;

        file_put_contents($contentDir . '/config.yaml', "title: Project Site\nbase_url: https://samdark.github.io/blog/\nlanguages: [en]\n");
        file_put_contents($contentDir . '/blog/_collection.yaml', "title: Blog\npermalink: /blog/:slug/\n");
        file_put_contents($contentDir . '/blog/assets/photo.jpg', 'jpg');
        file_put_contents($contentDir . '/blog/post.md', "---\ntitle: Post\nimage: /blog/assets/photo.jpg\n---\n\n![](/blog/assets/photo.jpg)\n");

        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));

        $html = file_get_contents($outputDir . '/blog/post/index.html');
        assertNotFalse($html);
        assertMatchesRegularExpression('~<img src="../../blog/assets/photo\.[a-f0-9]{12}\.jpg" alt="">~', $html);
        assertStringContainsString('content="https://samdark.github.io/blog/blog/assets/photo.jpg"', $html);
    }

    public function testBuildUsesUniformInternalUrlsForSubdirectoryDeployment(): void
    {
        $tempDir = sys_get_temp_dir() . '/yiipress-build-project-url-test-' . uniqid();
        $contentDir = $tempDir . '/content';
        $outputDir = $tempDir . '/output';
        mkdir($contentDir . '/blog', 0o755, true);
        mkdir($contentDir . '/authors', 0o755, true);
        $this->tempContentDirs[] = $tempDir;

        file_put_contents(
            $contentDir . '/config.yaml',
            "title: Project Site\n"
            . "base_url: https://samdark.github.io/blog/\n"
            . "languages: [en]\n"
            . "author_pages: true\n"
            . "search: true\n"
            . "taxonomies:\n"
            . "  - tags\n"
            . "  - categories\n",
        );
        file_put_contents($contentDir . '/blog/_collection.yaml', "title: Blog\npermalink: /blog/:slug/\nfeed: true\n");
        file_put_contents($contentDir . '/authors/john-doe.md', "---\ntitle: John Doe\n---\n\nAuthor bio.\n");
        file_put_contents(
            $contentDir . '/blog/post.md',
            "---\n"
            . "title: Post\n"
            . "date: 2024-03-15\n"
            . "tags: [php, yii]\n"
            . "categories: [performance]\n"
            . "authors: [john-doe]\n"
            . "---\n\n"
            . "Inline #php tag.\n",
        );

        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($outputDir)
            . ' --no-cache'
            . ' 2>&1',
            $output,
            $exitCode,
        );

        assertSame(0, $exitCode, implode("\n", $output));

        $entryHtml = file_get_contents($outputDir . '/blog/post/index.html');
        assertNotFalse($entryHtml);
        assertStringContainsString('href="../../"', $entryHtml);
        assertStringContainsString('data-root="../../"', $entryHtml);
        assertStringContainsString('href="../../authors/john-doe/"', $entryHtml);
        assertStringContainsString('href="../../tags/php/" class="tag-link">#php</a>', $entryHtml);
        assertStringContainsString('href="../../tags/yii/" class="tag-link">#yii</a>', $entryHtml);
        assertStringContainsString('href="../../categories/performance/" class="category">performance</a>', $entryHtml);
        assertStringNotContainsString('href="/tags/', $entryHtml);
        assertStringNotContainsString('href="/categories/', $entryHtml);
        assertStringNotContainsString('href="/authors/', $entryHtml);

        $listingHtml = file_get_contents($outputDir . '/blog/index.html');
        assertNotFalse($listingHtml);
        assertStringContainsString('href="../blog/archive/"', $listingHtml);
        assertStringContainsString('href="../blog/rss.xml"', $listingHtml);
        assertStringContainsString('href="../blog/feed.xml"', $listingHtml);

        $tagsIndexHtml = file_get_contents($outputDir . '/tags/index.html');
        assertNotFalse($tagsIndexHtml);
        assertStringContainsString('href="../tags/php/"', $tagsIndexHtml);
        assertStringContainsString('href="../tags/yii/"', $tagsIndexHtml);

        $authorsIndexHtml = file_get_contents($outputDir . '/authors/index.html');
        assertNotFalse($authorsIndexHtml);
        assertStringContainsString('href="../authors/john-doe/"', $authorsIndexHtml);

        $feed = file_get_contents($outputDir . '/blog/feed.xml');
        assertNotFalse($feed);
        assertStringContainsString('https://samdark.github.io/blog/blog/post/', $feed);
        assertStringContainsString('href=&quot;https://samdark.github.io/blog/tags/php/&quot;', $feed);
        assertStringNotContainsString('href=&quot;/tags/php/&quot;', $feed);

        $sitemap = file_get_contents($outputDir . '/sitemap.xml');
        assertNotFalse($sitemap);
        assertStringContainsString('https://samdark.github.io/blog/blog/post/', $sitemap);
        assertStringContainsString('https://samdark.github.io/blog/authors/john-doe/', $sitemap);
    }

    private function manifestPath(): string
    {
        return RuntimePaths::cachePath(dirname(__DIR__, 3)) . '/build-manifest-' . hash('xxh128', $this->outputDir) . '.json';
    }

    private function runBuild(string $contentDir, string $extraOptions = ''): void
    {
        $result = $this->runBuildResult($contentDir, $extraOptions);

        assertSame(0, $result['exitCode'], 'Build failed: ' . $result['output']);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function runBuildResult(string $contentDir, string $extraOptions = ''): array
    {
        $yii = dirname(__DIR__, 3) . '/yii';
        exec(
            $yii . ' build'
            . ' --content-dir=' . escapeshellarg($contentDir)
            . ' --output-dir=' . escapeshellarg($this->outputDir)
            . ($extraOptions !== '' ? ' ' . $extraOptions : '')
            . ' 2>&1',
            $output,
            $exitCode,
        );

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }

    /**
     * @param array<string, string> $files
     */
    private function createMinimalContent(array $files): string
    {
        $contentDir = sys_get_temp_dir() . '/yiipress-minimal-content-' . uniqid();
        mkdir($contentDir, 0o755, true);
        file_put_contents($contentDir . '/config.yaml', "title: Test Site\nbase_url: https://example.com\nlanguages: [en]\n");
        foreach ($files as $relativePath => $contents) {
            $filePath = $contentDir . '/' . $relativePath;
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($filePath, $contents);
        }
        $this->tempContentDirs[] = $contentDir;

        return $contentDir;
    }

    private function copyContentFixture(): string
    {
        $sourceDir = dirname(__DIR__, 2) . '/Support/Data/content';
        $targetDir = sys_get_temp_dir() . '/yiipress-content-test-' . uniqid();
        $this->copyDir($sourceDir, $targetDir);
        $this->tempContentDirs[] = $targetDir;

        return $targetDir;
    }

    private function copyDir(string $sourceDir, string $targetDir): void
    {
        mkdir($targetDir, 0o755, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $targetPath = $targetDir . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                mkdir($targetPath, 0o755, true);
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
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
