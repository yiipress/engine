<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\AssetUrlRewriter;

#[BeforeMethods('setUp')]
#[Iterations(5)]
#[Revs(100)]
#[Warmup(1)]
final class AssetUrlRewriterBench
{
    private AssetUrlRewriter $rewriter;
    private string $html;

    /** @param array{assets: int} $params */
    public function setUp(array $params): void
    {
        $manifest = new AssetFingerprintManifest();
        for ($i = 0; $i < $params['assets']; $i++) {
            $manifest->register('assets/theme/style-' . $i . '.css', __FILE__);
        }
        $this->rewriter = new AssetUrlRewriter($manifest);
        $this->html = '<link href="' . $manifest->resolve('assets/theme/style-0.css') . '">'
            . str_repeat('<p>A paragraph with text and a <a href="/another-page/">link</a>.</p>', 400);
    }

    /** @return iterable<string, array{assets: int}> */
    public function provideSizes(): iterable
    {
        foreach ([12, 100, 1000] as $assets) {
            yield $assets . ' assets' => ['assets' => $assets];
        }
    }

    #[ParamProviders('provideSizes')]
    public function benchAlreadyFingerprintedPage(): void
    {
        $this->rewriter->rewrite($this->html);
    }
}
