<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Theme;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Extension\Theme\UsedThemes;

use function copy;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;

use const JSON_THROW_ON_ERROR;

final class UsedThemesTest extends TestCase
{
    private string $base;
    private string $contentDir;
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/yiipress-used-themes-test-' . uniqid();
        $this->contentDir = $this->base . '/content';
        $this->cacheFile = $this->base . '/runtime/cache/used-themes.json';
        mkdir($this->contentDir . '/blog', 0o755, true);
        file_put_contents($this->contentDir . '/config.yaml', "title: Blog\n");
        file_put_contents($this->contentDir . '/blog/_collection.yaml', "title: Blog\n");
        $this->writeEntry('blog/first.md', 'wonder');
        $this->writeEntry('blog/second.md', '');
        $this->writeEntry('about.md', 'calm');
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->base);
    }

    public function testFindsThemesSelectedByEntriesAndPages(): void
    {
        $used = new UsedThemes($this->cacheFile)->find(['wonder', 'calm', 'spare'], $this->contentDir);

        self::assertSame(['wonder', 'calm'], $used);
    }

    public function testUnchangedContentIsAnsweredFromCache(): void
    {
        $this->laterScan()->find(['wonder'], $this->contentDir);
        $this->rewriteCachedThemes(['spare']);

        $used = $this->laterScan()->find(['wonder', 'spare'], $this->contentDir);

        self::assertSame(['spare'], $used);
    }

    public function testEntryEditedInTheSecondOfTheScanWithSameSizeAndTimesIsScannedAgain(): void
    {
        $this->writeEntry('blog/first.md', 'aaaaaa');
        new UsedThemes($this->cacheFile)->find(['aaaaaa', 'wonder'], $this->contentDir);
        $this->writeEntry('blog/first.md', 'wonder');
        touch($this->contentDir . '/blog/first.md', (int) filemtime($this->contentDir . '/blog/first.md'));

        $used = new UsedThemes($this->cacheFile)->find(['aaaaaa', 'wonder'], $this->contentDir);

        self::assertSame(['wonder'], $used);
    }

    public function testCacheIsNotTrustedForFilesChangedInTheSecondOfTheScan(): void
    {
        new UsedThemes($this->cacheFile)->find(['wonder'], $this->contentDir);
        $this->rewriteCachedThemes(['spare']);

        $used = new UsedThemes($this->cacheFile)->find(['wonder', 'spare'], $this->contentDir);

        self::assertSame(['wonder'], $used);
    }

    public function testEditInTheSecondOfTheScanIsSeenWhenLocalClockRunsAheadOfFileTimes(): void
    {
        $fileSecond = time();
        $this->writeEntry('blog/first.md', 'aaaaaa');
        touch($this->contentDir . '/blog/first.md', $fileSecond);
        $skewed = new UsedThemes($this->cacheFile, static fn(): int => $fileSecond + 3);
        $skewed->find(['aaaaaa', 'wonder'], $this->contentDir);
        $this->writeEntry('blog/first.md', 'wonder');
        touch($this->contentDir . '/blog/first.md', $fileSecond);

        $used = $skewed->find(['aaaaaa', 'wonder'], $this->contentDir);

        self::assertSame(['wonder'], $used);
    }

    public function testRewriteWithRestoredModificationTimeIsSeenWhereChangeTimeIsCreationTime(): void
    {
        $entry = $this->contentDir . '/blog/first.md';
        $this->writeEntry('blog/first.md', 'aaaaaa');
        $modified = time() - 600;
        touch($entry, $modified);
        $this->windowsScan()->find(['aaaaaa', 'wonder'], $this->contentDir);
        $this->writeEntry('blog/first.md', 'wonder');
        touch($entry, $modified);

        $used = $this->windowsScan()->find(['aaaaaa', 'wonder'], $this->contentDir);

        self::assertSame(['wonder'], $used);
    }

    public function testChangeTimeAndInodeAreNotTrustedWhereChangeTimeIsCreationTime(): void
    {
        $entry = $this->contentDir . '/blog/first.md';
        $modified = time() - 600;
        touch($entry, $modified);
        $this->windowsScan()->find(['wonder'], $this->contentDir);
        $this->rewriteCachedThemes(['spare']);
        // Same contents and modification time in a new file: only the change time and the inode differ.
        copy($entry, $entry . '.new');
        rename($entry . '.new', $entry);
        touch($entry, $modified);

        $used = $this->windowsScan()->find(['wonder', 'spare'], $this->contentDir);

        self::assertSame(['spare'], $used);
    }

    public function testAddedEntryIsScannedAgain(): void
    {
        $this->laterScan()->find(['spare'], $this->contentDir);
        $this->writeEntry('blog/third.md', 'spare');

        $used = $this->laterScan()->find(['spare'], $this->contentDir);

        self::assertSame(['spare'], $used);
    }

    public function testBrokenCacheIsIgnored(): void
    {
        mkdir($this->base . '/runtime/cache', 0o755, true);
        file_put_contents($this->cacheFile, '{not json');

        $used = new UsedThemes($this->cacheFile)->find(['wonder'], $this->contentDir);

        self::assertSame(['wonder'], $used);
    }

    public function testBrokenContentKeepsEveryCandidate(): void
    {
        file_put_contents($this->contentDir . '/blog/broken.md', "---\ntitle: Broken\ndate: not a date\n---\nBody\n");

        $used = new UsedThemes($this->cacheFile)->find(['wonder', 'spare'], $this->contentDir);

        self::assertSame(['wonder', 'spare'], $used);
    }

    /**
     * A scan that runs long enough after the files were written that their times can be trusted.
     */
    private function laterScan(): UsedThemes
    {
        return new UsedThemes($this->cacheFile, static fn(): int => time() + UsedThemes::CLOCK_SKEW_SECONDS + 1);
    }

    /**
     * A later scan as on Windows, where PHP reports the creation time as `ctime`.
     */
    private function windowsScan(): UsedThemes
    {
        return new UsedThemes(
            $this->cacheFile,
            static fn(): int => time() + UsedThemes::CLOCK_SKEW_SECONDS + 1,
            changeTimeIsCreationTime: true,
        );
    }

    private function writeEntry(string $path, string $theme): void
    {
        $themeLine = $theme === '' ? '' : "theme: $theme\n";
        file_put_contents($this->contentDir . '/' . $path, "---\ntitle: Entry\n{$themeLine}---\nBody\n");
    }

    /**
     * @param list<string> $themes
     */
    private function rewriteCachedThemes(array $themes): void
    {
        $cache = json_decode((string) file_get_contents($this->cacheFile), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($cache);
        $cache['themes'] = $themes;
        file_put_contents($this->cacheFile, json_encode($cache, JSON_THROW_ON_ERROR));
    }
}
