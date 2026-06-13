<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Import\Jekyll\JekyllContentImporter;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class JekyllImporterBench
{
    private string $sourceDir;
    private string $targetDir;
    private JekyllContentImporter $importer;

    public function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-jekyll-bench-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-jekyll-bench-target-' . uniqid();
        mkdir($this->sourceDir . '/_posts', 0o755, true);
        mkdir($this->targetDir, 0o755, true);

        for ($i = 1; $i <= 100; $i++) {
            file_put_contents(
                $this->sourceDir . '/_posts/2024-03-' . str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT) . '-post-' . $i . '.md',
                "---\ntitle: Post $i\ntags: [php, yii]\n---\n\nBody $i.\n",
            );
        }

        $this->importer = new JekyllContentImporter();
    }

    public function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->targetDir);
    }

    #[Revs(10)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchImportPosts(): void
    {
        $this->removeDir($this->targetDir);
        mkdir($this->targetDir, 0o755, true);

        $this->importer->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');
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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
