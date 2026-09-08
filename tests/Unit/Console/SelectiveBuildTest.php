<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\RuntimePaths;

final class SelectiveBuildTest extends TestCase
{
    private string $directory;
    private string $content;
    private string $output;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yiipress-selective-' . uniqid();
        $this->content = $this->directory . '/content';
        $this->output = $this->directory . '/output';
        mkdir($this->content . '/blog', 0o755, true);
        mkdir($this->content . '/authors');
        file_put_contents($this->content . '/config.yaml', "title: Selective Test\nbase_url: https://example.com\nlanguages: [en]\nauthor_pages: true\ntaxonomies: [tags]\nsearch: true\n");
        file_put_contents($this->content . '/blog/_collection.yaml', "title: Blog\npermalink: /blog/:slug/\nentries_per_page: 2\nfeed: true\nfeed_limit: 2\n");
        file_put_contents($this->content . '/authors/alice.md', "---\ntitle: Alice\n---\nAuthor biography.\n");
        for ($i = 1; $i <= 25; ++$i) {
            file_put_contents($this->entry($i), sprintf("---\ntitle: Entry %d\nslug: entry-%d\ndate: 2024-01-%02d\ntags: [common, tag-%d]\nauthors: [alice]\n---\nBody for entry %d. [Other](blog/entry-2.md)\n", $i, $i, $i, $i, $i));
        }
    }

    protected function tearDown(): void
    {
        foreach ([$this->output, $this->directory . '/clean'] as $output) {
            foreach (['build-manifest-', 'shared-output-'] as $prefix) {
                $path = RuntimePaths::cachePath(dirname(__DIR__, 3)) . '/' . $prefix . hash('xxh128', $output) . '.json';
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        DirectoryRemover::remove($this->directory);
    }

    private function entry(int $number): string
    {
        return $this->content . '/blog/entry-' . $number . '.md';
    }

    private function build(string $options = '', ?string $output = null): string
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/yii')
            . ' build --workers=1 --content-dir=' . escapeshellarg($this->content)
            . ' --output-dir=' . escapeshellarg($output ?? $this->output) . ' ' . $options . ' 2>&1';
        exec($command, $lines, $exitCode);
        $text = implode("\n", $lines);
        self::assertSame(0, $exitCode, $text);
        return $text;
    }

    /** @return array<string, string> */
    private function hashes(string $directory): array
    {
        $hashes = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                $hashes[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($hashes);
        return $hashes;
    }

    private function assertMatchesCleanBuild(string $options = ''): void
    {
        $this->build('--no-cache ' . $options, $this->directory . '/clean');
        self::assertSame($this->hashes($this->directory . '/clean'), $this->hashes($this->output));
    }

    public function testOldEntryBodyEditLeavesUnrelatedPagesAndLimitedFeedsUntouched(): void
    {
        $this->build();
        $unchanged = ['blog/index.html', 'blog/feed.xml', 'feed.xml', 'sitemap.xml', '404.html', 'authors/index.html'];
        foreach ($unchanged as $relative) {
            touch($this->output . '/' . $relative, 100);
        }
        file_put_contents($this->entry(1), "\nExtra body text.\n", FILE_APPEND);
        $result = $this->build();
        self::assertStringContainsString('Entries written: 1 ', $result);
        self::assertStringContainsString('Listing pages: 1', $result);
        clearstatcache();
        foreach ($unchanged as $relative) {
            self::assertSame(100, filemtime($this->output . '/' . $relative), $relative);
        }
        $this->assertMatchesCleanBuild();
    }

    /** @return iterable<string, array{string}> */
    public static function changes(): iterable
    {
        foreach (['title', 'missing_title', 'body_same_size', 'date', 'permalink', 'delete', 'draft', 'future', 'tags', 'author', 'author_profile', 'delete_author', 'delete_navigation', 'add', 'config', 'asset', 'template', 'remove_collection'] as $change) {
            yield $change => [$change];
        }
    }

    #[DataProvider('changes')]
    public function testIncrementalOutputMatchesCleanBuildAfterChanges(string $change): void
    {
        if ($change === 'delete_navigation') {
            file_put_contents($this->content . '/navigation.yaml', "main:\n  - label: About\n    url: /about/\n");
        }
        $this->build();
        $path = $this->entry(2);
        $contents = (string) file_get_contents($path);
        switch ($change) {
            case 'title':
                file_put_contents($path, str_replace('title: Entry 2', 'title: Renamed entry', $contents));
                break;
            case 'missing_title':
                file_put_contents($path, str_replace('title: Entry 2', 'title: ""', $contents));
                break;
            case 'body_same_size':
                $mtime = filemtime($path);
                file_put_contents($path, str_replace('Body for', 'Text for', $contents));
                touch($path, $mtime);
                break;
            case 'date':
                file_put_contents($path, str_replace('2024-01-02', '2023-02-15', $contents));
                break;
            case 'permalink':
                file_put_contents($path, str_replace('slug: entry-2', "slug: entry-2\npermalink: /moved/", $contents));
                break;
            case 'delete':
                unlink($path);
                touch($this->content . '/blog', time() + 2);
                break;
            case 'draft':
            case 'future':
                file_put_contents($path, str_replace('date: 2024-01-02', $change === 'draft' ? "draft: true\ndate: 2024-01-02" : 'date: 2999-01-01', $contents));
                break;
            case 'tags':
                file_put_contents($path, str_replace('common, tag-2', 'replacement', $contents));
                break;
            case 'author':
                file_put_contents($path, str_replace('authors: [alice]', 'authors: []', $contents));
                break;
            case 'author_profile':
                file_put_contents($this->content . '/authors/alice.md', "---\ntitle: Alice Renamed\n---\nUpdated biography.\n");
                break;
            case 'delete_author':
                unlink($this->content . '/authors/alice.md');
                break;
            case 'delete_navigation':
                unlink($this->content . '/navigation.yaml');
                break;
            case 'add':
                $directoryMtime = filemtime($this->content . '/blog');
                file_put_contents($this->entry(26), str_replace(['Entry 2', 'entry-2', '2024-01-02'], ['Entry 26', 'entry-26', '2024-02-26'], $contents));
                touch($this->content . '/blog', $directoryMtime);
                break;
            case 'config':
                file_put_contents($this->content . '/config.yaml', "entries_per_page: 3\n", FILE_APPEND);
                file_put_contents($this->content . '/blog/_collection.yaml', "title: Blog\npermalink: /blog/:slug/\nentries_per_page: 3\nfeed: false\n");
                break;
            case 'asset':
                mkdir($this->content . '/assets');
                file_put_contents($this->content . '/assets/style.css', 'body{color:red}');
                touch($this->content, time() + 2);
                break;
            case 'template':
                file_put_contents($this->content . '/config.yaml', "theme: local\n", FILE_APPEND);
                mkdir($this->content . '/templates');
                file_put_contents($this->content . '/templates/collection_listing.php', '<?php echo "Custom " . $collectionTitle;');
                touch($this->content, time() + 2);
                break;
            case 'remove_collection':
                DirectoryRemover::remove($this->content . '/blog');
                touch($this->content, time() + 2);
                break;
        }
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testMissingSharedOutputIsRebuiltWithoutSourceChanges(): void
    {
        $this->build();
        unlink($this->output . '/blog/page/2/index.html');
        unlink($this->output . '/blog/feed.xml');
        unlink($this->output . '/sitemap.xml');
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testCustomTemplateReadingAnotherEntryIsConservativelyInvalidated(): void
    {
        file_put_contents($this->content . '/config.yaml', "theme: local\n", FILE_APPEND);
        mkdir($this->content . '/templates');
        file_put_contents($this->content . '/templates/collection_listing.php', '<?php echo file_get_contents(__DIR__ . "/../blog/entry-1.md");');
        $this->build();
        file_put_contents($this->entry(1), "\nChanged external dependency.\n", FILE_APPEND);
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testCustomFeedProcessorDependingOnAnotherEntryIsInvalidated(): void
    {
        mkdir($this->content . '/processors');
        file_put_contents(
            $this->content . '/processors/external.php',
            '<?php return static fn(string $content): string => $content . "\n" . file_get_contents(__DIR__ . "/../blog/entry-1.md");',
        );
        $this->build();
        file_put_contents($this->entry(1), "\nChanged processor input.\n", FILE_APPEND);
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testNewReferenceTargetRebuildsPreviouslyBrokenLinks(): void
    {
        file_put_contents($this->entry(25), "\n[New page](blog/entry-26.md)\n", FILE_APPEND);
        $this->build();
        file_put_contents($this->entry(26), "---\ntitle: New page\nslug: entry-26\ndate: 2023-02-26\n---\nNew page.\n");
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testMovingStandalonePageUpdatesIncomingReferences(): void
    {
        file_put_contents($this->content . '/about.md', "---\ntitle: About\n---\nAbout.\n");
        file_put_contents($this->entry(1), "\n[About](about.md)\n", FILE_APPEND);
        $this->build();
        file_put_contents($this->content . '/about.md', "---\ntitle: About\npermalink: /moved-about/\n---\nAbout.\n");
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testFutureEntriesAndStandalonePagesPublishWithoutSourceEdits(): void
    {
        $publishAt = time() + 3;
        $date = date('c', $publishAt);
        file_put_contents($this->entry(1), str_replace('2024-01-01', $date, (string) file_get_contents($this->entry(1))));
        file_put_contents($this->content . '/about.md', "---\ntitle: About\ndate: $date\n---\nFuture page.\n");
        $this->build();
        self::assertFileDoesNotExist($this->output . '/blog/entry-1/index.html');
        self::assertFileDoesNotExist($this->output . '/about/index.html');
        $remaining = $publishAt - time() + 1;
        if ($remaining > 0) {
            sleep($remaining);
        }
        $this->build();
        self::assertFileExists($this->output . '/blog/entry-1/index.html');
        self::assertFileExists($this->output . '/about/index.html');
        $this->assertMatchesCleanBuild();
    }

    public function testObsoleteArchiveYearAndMonthAreRemoved(): void
    {
        $this->build();
        $original = (string) file_get_contents($this->entry(2));
        file_put_contents($this->entry(2), str_replace('2024-01-02', '2023-02-15', $original));
        $this->build();
        self::assertFileExists($this->output . '/blog/2023/02/index.html');
        file_put_contents($this->entry(2), $original);
        $this->build();
        self::assertFileDoesNotExist($this->output . '/blog/2023/index.html');
        self::assertFileDoesNotExist($this->output . '/blog/2023/02/index.html');
        $this->assertMatchesCleanBuild();
    }

    public function testCorruptSharedInventoryFallsBackToCleanReplacement(): void
    {
        $this->build();
        $cache = RuntimePaths::cachePath(dirname(__DIR__, 3)) . '/shared-output-' . hash('xxh128', $this->output) . '.json';
        file_put_contents($cache, 'invalid');
        unlink($this->entry(2));
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testNoCacheBuildCannotLeaveAnOlderSourceManifestLookingCurrent(): void
    {
        $this->build();
        $original = (string) file_get_contents($this->entry(1));
        file_put_contents($this->entry(1), $original . "\nTemporary edit.\n");
        $this->build('--no-cache');
        file_put_contents($this->entry(1), $original);
        $this->build();
        $this->assertMatchesCleanBuild();
    }

    public function testChangingDraftFlagDoesNotLeavePreviouslyPublishedOutputs(): void
    {
        file_put_contents($this->entry(1), str_replace('date:', "draft: true\ndate:", (string) file_get_contents($this->entry(1))));
        $this->build('--drafts');
        self::assertFileExists($this->output . '/blog/entry-1/index.html');
        $this->build();
        self::assertFileDoesNotExist($this->output . '/blog/entry-1/index.html');
        $this->assertMatchesCleanBuild();
    }
}
