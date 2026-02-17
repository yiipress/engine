<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\ContentAssetCopier;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringEqualsFile;

final class ContentAssetCopierTest extends TestCase
{
    private string $contentDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-asset-test-content-' . uniqid();
        $this->outputDir = sys_get_temp_dir() . '/yiipress-asset-test-output-' . uniqid();

        mkdir($this->contentDir . '/blog/assets', 0o755, true);
        mkdir($this->contentDir . '/authors', 0o755, true);
        mkdir($this->outputDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
        $this->removeDir($this->outputDir);
    }

    public function testCopiesAssetFilesToOutput(): void
    {
        file_put_contents($this->contentDir . '/blog/assets/banner.svg', '<svg/>');

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir);

        assertSame(1, $copied);
        assertFileExists($this->outputDir . '/blog/assets/banner.svg');
        assertStringEqualsFile($this->outputDir . '/blog/assets/banner.svg', '<svg/>');
    }

    public function testSkipsMarkdownFiles(): void
    {
        file_put_contents($this->contentDir . '/blog/post.md', '# Hello');

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir);

        assertSame(0, $copied);
    }

    public function testSkipsYamlFiles(): void
    {
        file_put_contents($this->contentDir . '/blog/_collection.yaml', 'name: blog');

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir);

        assertSame(0, $copied);
    }

    public function testSkipsAuthorsDirectory(): void
    {
        file_put_contents($this->contentDir . '/authors/photo.jpg', 'binary');

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir);

        assertSame(0, $copied);
    }

    public function testCollectReturnsRelativePaths(): void
    {
        file_put_contents($this->contentDir . '/blog/assets/banner.svg', '<svg/>');
        file_put_contents($this->contentDir . '/blog/assets/photo.png', 'binary');
        file_put_contents($this->contentDir . '/blog/post.md', '# Hello');

        $copier = new ContentAssetCopier();
        $assets = $copier->collect($this->contentDir);

        assertCount(2, $assets);
        assertSame('blog/assets/banner.svg', $assets[0]);
        assertSame('blog/assets/photo.png', $assets[1]);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
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
        rmdir($path);
    }
}
