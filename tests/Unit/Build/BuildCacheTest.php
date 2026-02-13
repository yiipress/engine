<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\BuildCache;
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
            $iterator = new \DirectoryIterator($this->cacheDir);
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
        $templatePath = dirname(__DIR__, 3) . '/src/Render/Template/entry.php';
        $cache = new BuildCache($this->cacheDir, $templatePath);

        assertNull($cache->get($this->fixtureDir . '/2024-03-15-test-post.md'));
    }

    public function testReturnCachedValueOnHit(): void
    {
        $templatePath = dirname(__DIR__, 3) . '/src/Render/Template/entry.php';
        $cache = new BuildCache($this->cacheDir, $templatePath);

        $sourceFile = $this->fixtureDir . '/2024-03-15-test-post.md';
        $cache->set($sourceFile, '<html>cached</html>');

        assertSame('<html>cached</html>', $cache->get($sourceFile));
    }

    public function testClearRemovesAllEntries(): void
    {
        $templatePath = dirname(__DIR__, 3) . '/src/Render/Template/entry.php';
        $cache = new BuildCache($this->cacheDir, $templatePath);

        $sourceFile = $this->fixtureDir . '/2024-03-15-test-post.md';
        $cache->set($sourceFile, '<html>cached</html>');
        $cache->clear();

        assertNull($cache->get($sourceFile));
    }
}
