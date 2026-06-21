<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\ContentAssetCopier;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    public function testMinifiesCssAndJavaScriptAssetsByDefault(): void
    {
        file_put_contents($this->contentDir . '/blog/assets/app.css', "/* theme */\nbody { color: red; }\n");
        file_put_contents($this->contentDir . '/blog/assets/app.js', "const value = 1; // keep code\nconsole.log(value);\n");

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir);

        assertSame(2, $copied);
        assertStringEqualsFile($this->outputDir . '/blog/assets/app.css', 'body{color:red}');
        assertStringEqualsFile($this->outputDir . '/blog/assets/app.js', "const value = 1; \nconsole.log(value);");
    }

    public function testCanCopyCssAndJavaScriptAssetsWithoutMinifying(): void
    {
        $css = "/* theme */\nbody { color: red; }\n";
        $js = "const value = 1; // keep code\nconsole.log(value);\n";
        file_put_contents($this->contentDir . '/blog/assets/app.css', $css);
        file_put_contents($this->contentDir . '/blog/assets/app.js', $js);

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir, minify: false);

        assertSame(2, $copied);
        assertStringEqualsFile($this->outputDir . '/blog/assets/app.css', $css);
        assertStringEqualsFile($this->outputDir . '/blog/assets/app.js', $js);
    }

    public function testSkipsUnchangedOutputAsset(): void
    {
        $source = $this->contentDir . '/blog/assets/banner.svg';
        file_put_contents($source, '<svg/>');
        touch($source, 1_700_000_000);

        $copier = new ContentAssetCopier();

        assertSame(1, $copier->copy($this->contentDir, $this->outputDir));
        assertSame(0, $copier->copy($this->contentDir, $this->outputDir));
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

    public function testCopiesFingerprintedAssetsWhenManifestProvided(): void
    {
        $source = $this->contentDir . '/blog/assets/banner.svg';
        file_put_contents($source, '<svg/>');

        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('blog/assets/banner.svg', $source);

        $copier = new ContentAssetCopier();
        $copied = $copier->copy($this->contentDir, $this->outputDir, $manifest);

        assertSame(1, $copied);
        assertFileExists($this->outputDir . '/' . $resolved);
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
