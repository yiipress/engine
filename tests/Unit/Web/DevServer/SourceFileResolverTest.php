<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\DevServer;

use YiiPress\Build\BuildManifest;
use YiiPress\Web\DevServer\SourceFileResolver;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function mkdir;
use function sys_get_temp_dir;

final class SourceFileResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-source-resolver-' . uniqid();
        mkdir($this->root . '/content/blog', 0o755, true);
        mkdir($this->root . '/output/blog/post', 0o755, true);
        mkdir($this->root . '/runtime/cache', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testResolvesServedPageToMarkdownSourceFile(): void
    {
        $sourceFile = $this->root . '/content/blog/post.md';
        $outputFile = $this->root . '/output/blog/post/index.html';
        file_put_contents($sourceFile, "---\ntitle: Post\n---\nBody");
        file_put_contents($outputFile, '<html><body>Post</body></html>');

        $manifest = new BuildManifest($this->root . '/runtime/cache/build-manifest.json');
        $manifest->record($sourceFile, [$outputFile]);
        $manifest->save();

        $resolver = new SourceFileResolver(
            $this->root . '/runtime/cache/build-manifest.json',
            $this->root . '/content',
            $this->root . '/output',
        );

        self::assertSame($sourceFile, $resolver->resolve('/blog/post/'));
    }

    public function testDoesNotResolveNonMarkdownManifestEntries(): void
    {
        $sourceFile = $this->root . '/content/blog/style.css';
        $outputFile = $this->root . '/output/blog/post/index.html';
        file_put_contents($sourceFile, 'body{}');
        file_put_contents($outputFile, '<html><body>Post</body></html>');

        $manifest = new BuildManifest($this->root . '/runtime/cache/build-manifest.json');
        $manifest->record($sourceFile, [$outputFile]);
        $manifest->save();

        $resolver = new SourceFileResolver(
            $this->root . '/runtime/cache/build-manifest.json',
            $this->root . '/content',
            $this->root . '/output',
        );

        self::assertNull($resolver->resolve('/blog/post/'));
    }

    public function testInvalidManifestDoesNotResolveSourceFile(): void
    {
        $outputFile = $this->root . '/output/blog/post/index.html';
        file_put_contents($outputFile, '<html><body>Post</body></html>');
        file_put_contents($this->root . '/runtime/cache/build-manifest.json', '{not-json');

        $resolver = new SourceFileResolver(
            $this->root . '/runtime/cache/build-manifest.json',
            $this->root . '/content',
            $this->root . '/output',
        );

        self::assertNull($resolver->resolve('/blog/post/'));
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
