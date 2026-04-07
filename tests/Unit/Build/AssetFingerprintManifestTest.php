<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\AssetFingerprintManifest;
use App\Build\AssetUrlRewriter;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
        assertSame('<link href="/' . $fingerprinted . '">', $absolute);
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
