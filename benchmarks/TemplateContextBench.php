<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\TemplateContext;
use YiiPress\Build\AssetFingerprintManifest;
use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class TemplateContextBench
{
    private TemplateContext $context;
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress-bench-partial-' . uniqid();
        mkdir($this->tempDir . '/partials', 0o755, true);

        file_put_contents(
            $this->tempDir . '/partials/simple.php',
            '<div><?= htmlspecialchars($title) ?></div>',
        );
        file_put_contents(
            $this->tempDir . '/partials/nested.php',
            '<header><?= $partial("simple", ["title" => $heading]) ?></header><main><?= $body ?></main>',
        );
        mkdir($this->tempDir . '/assets');
        file_put_contents($this->tempDir . '/assets/style.css', 'body{}');
        file_put_contents(
            $this->tempDir . '/partials/assets.php',
            '<?php for ($i = 0; $i < 6; ++$i): ?><link href="<?= $themeAsset("style.css") ?>"><?php endfor ?>',
        );

        $registry = new ThemeRegistry();
        $registry->register(new Theme('bench', $this->tempDir));
        $resolver = new TemplateResolver($registry);
        $manifest = new AssetFingerprintManifest();
        $manifest->register('assets/themes/bench/style.css', $this->tempDir . '/assets/style.css');
        $this->context = new TemplateContext($resolver, 'bench', $manifest);
    }

    public function tearDown(): void
    {
        @unlink($this->tempDir . '/partials/simple.php');
        @unlink($this->tempDir . '/partials/nested.php');
        @unlink($this->tempDir . '/partials/assets.php');
        @unlink($this->tempDir . '/assets/style.css');
        @rmdir($this->tempDir . '/assets');
        @rmdir($this->tempDir . '/partials');
        @rmdir($this->tempDir);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchSimplePartial(): void
    {
        $this->context->partial('simple', ['title' => 'Hello World']);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchNestedPartial(): void
    {
        $this->context->partial('nested', ['heading' => 'Title', 'body' => '<p>Content</p>']);
    }

    #[Revs(1000)]
    #[Iterations(5)]
    #[Warmup(1)]
    public function benchThemeAssetPartial(): void
    {
        $this->context->partial('assets', ['rootPath' => '../../']);
    }
}
