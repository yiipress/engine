<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\AssetUrlRewriter;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use RuntimeException;

use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class AssetFingerprintManifestTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-asset-fingerprint-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRegisterGeneratesFingerprintedPath(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');

        $manifest = new AssetFingerprintManifest();
        $resolved = $manifest->register('assets/theme/style.css', $source);

        assertNotSame('assets/theme/style.css', $resolved);
        assertStringContainsString('assets/theme/style.', $resolved);
        assertSame($resolved, $manifest->resolve('assets/theme/style.css'));
    }

    public function testLogicalPathMatcherUsesLiteralCaseSensitivePaths(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        assertSame(false, $manifest->containsLogicalPath('assets/a+[b]~.css'));
        $manifest->register('assets/a+[b]~.css', $source);

        assertSame(true, $manifest->containsLogicalPath('<link href="/assets/a+[b]~.css?v=1">'));
        assertSame(false, $manifest->containsLogicalPath('assets/A+[b]~.css'));
        assertSame(false, $manifest->containsLogicalPath('assets/aaab~Xcss'));
        assertSame(false, $manifest->containsLogicalPath(''));
    }

    public function testRewriterSeesPathsRegisteredAfterFirstRewrite(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/first.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);
        $html = '<link href="assets/second.css">';
        assertSame($html, $rewriter->rewrite($html));

        $fingerprinted = $manifest->register('assets/second.css', $source);
        assertSame('<link href="' . $fingerprinted . '">', $rewriter->rewrite($html));
        assertSame(true, $manifest->containsLogicalPath('assets/first.css'));
    }

    public function testLogicalPathMatcherFallsBackForOversizedPatterns(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'css');
        $manifest = new AssetFingerprintManifest();
        for ($i = 0; $i < 1000; $i++) {
            $manifest->register('assets/' . str_repeat('a', 200) . $i . '.css', $source);
        }
        $lastPath = 'assets/' . str_repeat('a', 200) . '999.css';
        assertSame(true, $manifest->containsLogicalPath($lastPath));
        assertSame(false, $manifest->containsLogicalPath('no assets here'));
        assertSame(true, $manifest->containsLogicalPath($lastPath));
        assertSame(
            '<link href="' . $manifest->resolve($lastPath) . '">',
            new AssetUrlRewriter($manifest)->rewrite('<link href="' . $lastPath . '">'),
        );

        $manifest->register('assets/late.css', $source);
        assertSame(true, $manifest->containsLogicalPath('assets/late.css'));
    }

    public function testRegisterRejectsMissingSource(): void
    {
        $manifest = new AssetFingerprintManifest();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to hash asset source file:');

        $manifest->register('assets/theme/missing.css', $this->tempDir . '/missing.css');
    }

    public function testRewriterUpdatesRelativeAndAbsoluteAssetUrls(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $relative = $rewriter->rewrite('<link href="../../assets/theme/style.css">', '../../');
        $absolute = $rewriter->rewrite('<link href="/assets/theme/style.css">', '../../');

        assertSame('<link href="../../' . $fingerprinted . '">', $relative);
        assertSame('<link href="../../' . $fingerprinted . '">', $absolute);
    }

    public function testRewriterRelativizesRootRelativeContentAssetUrls(): void
    {
        $source = $this->tempDir . '/photo.jpg';
        file_put_contents($source, 'jpg');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('blog/assets/photo.jpg', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = $rewriter->rewrite('<img src="/blog/assets/photo.jpg">', '../../');

        assertSame('<img src="../../' . $fingerprinted . '">', $html);
    }

    public function testRewriterPrefixesUnqualifiedAssetUrlsWithRootPath(): void
    {
        $source = $this->tempDir . '/mermaid.css';
        file_put_contents($source, '.mermaid{}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/plugins/mermaid.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = $rewriter->rewrite('<link href="assets/plugins/mermaid.css">', '../');

        assertSame('<link href="../' . $fingerprinted . '">', $html);
    }

    public function testRewriterSkipsHtmlWithoutAssetReferences(): void
    {
        $manifest = new AssetFingerprintManifest();
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');
        $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = '<div><p>No local asset URLs here.</p></div>';

        assertSame($html, $rewriter->rewrite($html, '../../'));
    }

    public function testRewriterSkipsHtmlWithAlreadyFingerprintedAssetUrls(): void
    {
        $manifest = new AssetFingerprintManifest();
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{color:red}');
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);
        $rewriter = new AssetUrlRewriter($manifest);

        $html = '<link href="../../' . $fingerprinted . '">';

        assertSame($html, $rewriter->rewrite($html, '../../'));
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
