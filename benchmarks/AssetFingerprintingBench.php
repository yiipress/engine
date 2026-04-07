<?php

declare(strict_types=1);

namespace App\Benchmarks;

use App\Build\AssetFingerprintManifest;
use App\Build\AssetUrlRewriter;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class AssetFingerprintingBench
{
    private AssetFingerprintManifest $manifest;
    private AssetUrlRewriter $rewriter;
    private string $tempDir;
    private string $html;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-bench-assets-' . uniqid();
        mkdir($this->tempDir, 0o755, true);

        file_put_contents($this->tempDir . '/style.css', 'body { color: red; }');
        file_put_contents($this->tempDir . '/search.css', '.search { display: block; }');
        file_put_contents($this->tempDir . '/search.js', 'window.search = true;');

        $this->manifest = new AssetFingerprintManifest();
        $this->manifest->register('assets/theme/style.css', $this->tempDir . '/style.css');
        $this->manifest->register('assets/theme/search.css', $this->tempDir . '/search.css');
        $this->manifest->register('assets/theme/search.js', $this->tempDir . '/search.js');
        $this->rewriter = new AssetUrlRewriter($this->manifest);

        $this->html = <<<'HTML'
<link rel="stylesheet" href="../../assets/theme/style.css">
<link rel="stylesheet" href="../../assets/theme/search.css">
<script src="../../assets/theme/search.js" defer></script>
HTML;
    }

    public function tearDown(): void
    {
        @unlink($this->tempDir . '/style.css');
        @unlink($this->tempDir . '/search.css');
        @unlink($this->tempDir . '/search.js');
        @rmdir($this->tempDir);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchResolveFingerprintedAssetPath(): void
    {
        $this->manifest->resolve('assets/theme/style.css');
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchRewriteHtmlSnippet(): void
    {
        $this->rewriter->rewrite($this->html, '../../');
    }
}
