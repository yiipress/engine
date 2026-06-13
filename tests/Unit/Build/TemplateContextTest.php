<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\Asset;
use YiiPress\Build\TemplateContext;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class TemplateContextTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-partial-test-' . uniqid();
        mkdir($this->tempDir . '/partials', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testRendersPartialWithVariables(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/greeting.php',
            'Hello, <?= $h($name) ?>!',
        );
        $context = $this->createContext();

        $result = $context->partial('greeting', ['name' => 'World']);

        assertSame('Hello, World!', $result);
    }

    public function testPartialHasIsolatedScope(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/scoped.php',
            '<?= isset($outside) ? "leaked" : "isolated" ?>',
        );
        $outside = 'should not leak';
        $context = $this->createContext();

        $result = $context->partial('scoped');

        assertSame('isolated', $result);
    }

    public function testPartialCanCallNestedPartials(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/outer.php',
            'before-<?= $partial("inner", ["x" => $value]) ?>-after',
        );
        file_put_contents(
            $this->tempDir . '/partials/inner.php',
            '<?= $x ?>',
        );
        $context = $this->createContext();

        $result = $context->partial('outer', ['value' => 'middle']);

        assertSame('before-middle-after', $result);
    }

    public function testThrowsForMissingPartial(): void
    {
        $context = $this->createContext();

        $this->expectException(RuntimeException::class);
        $context->partial('nonexistent');
    }

    public function testPartialEscapesHtmlByDefault(): void
    {
        file_put_contents(
            $this->tempDir . '/partials/escape.php',
            '<?= $h($text) ?>',
        );
        $context = $this->createContext();

        $result = $context->partial('escape', ['text' => '<script>alert(1)</script>']);

        assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testStaticAssetHelperReturnsFingerprintedPath(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);

        assertSame('../../' . $fingerprinted, Asset::url('assets/theme/style.css', '../../', $manifest));
    }

    public function testThemeAssetHelperReturnsFingerprintedNamespacedThemeAsset(): void
    {
        mkdir($this->tempDir . '/assets', 0o755, true);
        file_put_contents(
            $this->tempDir . '/partials/asset.php',
            '<?= $themeAsset("style.css") ?>',
        );
        $source = $this->tempDir . '/assets/style.css';
        file_put_contents($source, 'body{}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/themes/test/style.css', $source);
        $context = $this->createContext($manifest);

        $result = $context->partial('asset', ['rootPath' => '../../']);

        assertSame('../../' . $fingerprinted, $result);
    }

    public function testRewriteHtmlUpdatesReferencedAssets(): void
    {
        $source = $this->tempDir . '/style.css';
        file_put_contents($source, 'body{}');

        $manifest = new AssetFingerprintManifest();
        $fingerprinted = $manifest->register('assets/theme/style.css', $source);

        $context = $this->createContext($manifest);

        $html = $context->rewriteHtml('<link href="../../assets/theme/style.css">', '../../');

        assertSame('<link href="../../' . $fingerprinted . '">', $html);
    }

    private function createContext(?AssetFingerprintManifest $assetManifest = null): TemplateContext
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $this->tempDir));
        $resolver = new TemplateResolver($registry);
        return new TemplateContext($resolver, 'test', $assetManifest);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
