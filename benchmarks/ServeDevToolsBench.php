<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\BuildManifest;
use YiiPress\Web\DevServer\DevHtmlInjector;
use YiiPress\Web\DevServer\SourceFileResolver;
use FilesystemIterator;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ServeDevToolsBench
{
    private string $root;
    private string $html;
    private SourceFileResolver $resolver;

    public function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yiipress-bench-serve-devtools-' . uniqid();
        mkdir($this->root . '/content/blog', 0o755, true);
        mkdir($this->root . '/output/blog/post', 0o755, true);
        mkdir($this->root . '/runtime/cache', 0o755, true);

        $sourceFile = $this->root . '/content/blog/post.md';
        $outputFile = $this->root . '/output/blog/post/index.html';
        file_put_contents($sourceFile, "---\ntitle: Post\n---\nBody");
        file_put_contents($outputFile, '<html><body>Post</body></html>');

        $manifest = new BuildManifest($this->root . '/runtime/cache/build-manifest.json');
        $manifest->record($sourceFile, [$outputFile]);
        $manifest->save();

        $this->resolver = new SourceFileResolver(
            $this->root . '/runtime/cache/build-manifest.json',
            $this->root . '/content',
            $this->root . '/output',
        );
        $this->html = '<!DOCTYPE html><html><body><main><h1>Post</h1><p>Body</p></main></body></html>';
    }

    public function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchInjectPreviewScripts(): void
    {
        DevHtmlInjector::inject($this->html);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchResolveMarkdownSource(): void
    {
        $this->resolver->resolve('/blog/post/');
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
