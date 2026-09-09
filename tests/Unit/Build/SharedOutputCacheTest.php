<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;
use YiiPress\Build\DirectoryRemover;
use YiiPress\Build\SharedOutputCache;
use YiiPress\Content\Parser\EntryParser;
use YiiPress\Content\Parser\FilenameParser;
use YiiPress\Content\Parser\FrontMatterParser;
use DateTimeImmutable;

final class SharedOutputCacheTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/yiipress-shared-' . uniqid();
        mkdir($this->directory);
        $this->path = $this->directory . '/state.json';
    }

    protected function tearDown(): void
    {
        DirectoryRemover::remove($this->directory);
    }

    private function cache(bool $reuse = true): SharedOutputCache
    {
        $cache = new SharedOutputCache($this->path, $this->directory);
        $cache->begin($this->directory, ['scope'], ['context'], $reuse);
        return $cache;
    }

    public function testReusesOnlyMatchingDependenciesWithExistingOutputs(): void
    {
        $cache = $this->cache();
        self::assertTrue($cache->needsWrite(['listing.html'], ['one', 'two']));
        file_put_contents($this->directory . '/listing.html', 'listing');
        $cache->save();
        $loaded = new SharedOutputCache($this->path, $this->directory);
        self::assertTrue($loaded->complete());
        self::assertTrue($loaded->matchesScope(['scope']));
        self::assertFalse($loaded->matchesScope(['different scope']));
        $loaded->begin($this->directory, ['scope'], ['context'], true);
        self::assertFalse($loaded->needsWrite(['listing.html'], ['one', 'two']));
        self::assertTrue($loaded->needsWrite(['listing.html'], ['two', 'one']));
        unlink($this->directory . '/listing.html');
        self::assertTrue($loaded->needsWrite(['listing.html'], ['one', 'two']));
    }

    public function testContextChangeAndConservativeModeInvalidateOutputs(): void
    {
        $cache = $this->cache();
        $cache->needsWrite(['page.html'], []);
        file_put_contents($this->directory . '/page.html', 'page');
        $cache->save();
        $loaded = new SharedOutputCache($this->path, $this->directory);
        $loaded->begin($this->directory, ['scope'], ['changed context'], true);
        self::assertTrue($loaded->needsWrite(['page.html'], []));
        $loaded->save();
        self::assertTrue($this->cache(false)->needsWrite(['page.html'], []));
    }

    public function testInterruptedBuildCannotReuseOldFingerprints(): void
    {
        $cache = $this->cache();
        $cache->needsWrite(['page.html'], ['old']);
        file_put_contents($this->directory . '/page.html', 'old');
        $cache->save();
        $incomplete = $this->cache();
        $incomplete->needsWrite(['page.html'], ['new']);
        file_put_contents($this->directory . '/page.html', 'new');
        self::assertFalse(new SharedOutputCache($this->path, $this->directory)->valid());
        self::assertTrue($this->cache()->needsWrite(['page.html'], ['old']));
    }

    public function testGroupsTrackEveryFileAndRemoveObsoleteFilesWithoutRemovingNewOwners(): void
    {
        $cache = $this->cache();
        $cache->recordGroup('sitemap', ['sitemap.xml', 'sitemap_2.xml'], ['old']);
        $cache->needsWrite(['old.html'], []);
        foreach (['sitemap.xml', 'sitemap_2.xml', 'old.html'] as $path) {
            file_put_contents($this->directory . '/' . $path, 'output');
        }
        $cache->save();
        $cache = $this->cache();
        self::assertFalse($cache->groupNeedsWrite('sitemap', ['old']));
        self::assertTrue($cache->groupNeedsWrite('sitemap', ['new']));
        $cache->recordGroup('sitemap', ['sitemap.xml'], ['new']);
        $cache->removeObsolete([$this->directory . '/old.html']);
        self::assertFileDoesNotExist($this->directory . '/sitemap_2.xml');
        self::assertFileExists($this->directory . '/sitemap.xml');
        self::assertFileExists($this->directory . '/old.html');
        $cache->save();
        unlink($this->directory . '/sitemap.xml');
        self::assertFalse(new SharedOutputCache($this->path, $this->directory)->complete());
    }

    public function testCorruptInventoryIsDiscardedWithoutFollowingItsPaths(): void
    {
        foreach (['not json', '{"version":1,"scope":"x","outputs":{"../outside":"hash"}}',
            '{"version":1,"scope":"x","outputs":{},"groups":{"sitemap":["missing"]}}'] as $contents) {
            file_put_contents($this->path, $contents);
            self::assertFalse(new SharedOutputCache($this->path, $this->directory)->valid());
        }
    }

    public function testPublicationDeadlineUsesTheBuildFilteringTime(): void
    {
        $source = $this->directory . '/entry.md';
        file_put_contents($source, "---\ntitle: Future entry\ndate: 2024-01-01\n---\nBody.\n");
        $entry = new EntryParser(new FrontMatterParser(), new FilenameParser())->parse($source, 'blog');
        $cache = $this->cache();
        $cache->trackFutureEntries([$entry], new DateTimeImmutable('2023-12-31'));
        $cache->save();
        // A publication time crossed during the build must trigger the next rebuild.
        self::assertFalse(new SharedOutputCache($this->path, $this->directory)->complete());
    }
}
