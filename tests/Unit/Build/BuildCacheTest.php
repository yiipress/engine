<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\BuildCache;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class BuildCacheTest extends TestCase
{
    private string $cacheDir;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->cacheDir = dirname(__DIR__, 2) . '/Support/Data/cache';
        $this->fixtureDir = dirname(__DIR__, 2) . '/Support/Data/content/blog';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $iterator = new DirectoryIterator($this->cacheDir);
            foreach ($iterator as $item) {
                if (!$item->isDot()) {
                    unlink($item->getPathname());
                }
            }
            rmdir($this->cacheDir);
        }
    }

    public function testReturnNullOnCacheMiss(): void
    {
        $templateDirs = [dirname(__DIR__, 3) . '/themes/minimal'];
        $cache = new BuildCache($this->cacheDir, $templateDirs);

        assertNull($cache->get($this->fixtureDir . '/2024-03-15-test-post.md'));
    }

    public function testReturnCachedValueOnHit(): void
    {
        $templateDirs = [dirname(__DIR__, 3) . '/themes/minimal'];
        $cache = new BuildCache($this->cacheDir, $templateDirs);

        $sourceFile = $this->fixtureDir . '/2024-03-15-test-post.md';
        $cache->set($sourceFile, '<html>cached</html>');

        assertSame('<html>cached</html>', $cache->get($sourceFile));
    }

    public function testClearRemovesAllEntries(): void
    {
        $templateDirs = [dirname(__DIR__, 3) . '/themes/minimal'];
        $cache = new BuildCache($this->cacheDir, $templateDirs);

        $sourceFile = $this->fixtureDir . '/2024-03-15-test-post.md';
        $cache->set($sourceFile, '<html>cached</html>');
        $cache->clear();

        assertNull($cache->get($sourceFile));
    }

    public function testTemplateHashIncludesNestedPartials(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/Support/Data/cache-templates';
        mkdir($templateDir . '/partials', 0o755, true);

        try {
            file_put_contents($templateDir . '/entry.php', 'entry');
            file_put_contents($templateDir . '/partials/head.php', 'old');

            $sourceFile = $this->fixtureDir . '/2024-03-15-test-post.md';
            $cache = new BuildCache($this->cacheDir, [$templateDir]);
            $cache->set($sourceFile, '<html>cached</html>');

            file_put_contents($templateDir . '/partials/head.php', 'new');

            $cache = new BuildCache($this->cacheDir, [$templateDir]);

            assertNull($cache->get($sourceFile));
        } finally {
            if (is_file($templateDir . '/partials/head.php')) {
                unlink($templateDir . '/partials/head.php');
            }
            if (is_file($templateDir . '/entry.php')) {
                unlink($templateDir . '/entry.php');
            }
            if (is_dir($templateDir . '/partials')) {
                rmdir($templateDir . '/partials');
            }
            if (is_dir($templateDir)) {
                rmdir($templateDir);
            }
        }
    }
}
