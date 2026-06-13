<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Import\Ghost\GhostContentImporter;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class GhostImporterBench
{
    private string $sourceDir;
    private string $sourceFile;
    private string $targetDir;
    private GhostContentImporter $importer;

    public function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-ghost-bench-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-ghost-bench-target-' . uniqid();
        mkdir($this->sourceDir, 0o755, true);
        mkdir($this->targetDir, 0o755, true);
        $this->sourceFile = $this->sourceDir . '/ghost.json';

        $posts = [];
        $postsTags = [];
        for ($i = 1; $i <= 100; $i++) {
            $posts[] = [
                'id' => 'post-' . $i,
                'title' => 'Post ' . $i,
                'slug' => 'post-' . $i,
                'status' => 'published',
                'type' => 'post',
                'published_at' => '2024-03-15 10:30:00',
                'custom_excerpt' => 'Summary ' . $i . '.',
                'html' => '<p>Body ' . $i . '.</p>',
            ];
            $postsTags[] = ['post_id' => 'post-' . $i, 'tag_id' => 'tag-php'];
        }

        file_put_contents(
            $this->sourceFile,
            json_encode([
                'db' => [[
                    'data' => [
                        'posts' => $posts,
                        'tags' => [
                            ['id' => 'tag-php', 'slug' => 'php', 'name' => 'PHP'],
                        ],
                        'posts_tags' => $postsTags,
                    ],
                ]],
            ], JSON_THROW_ON_ERROR),
        );

        $this->importer = new GhostContentImporter();
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

        $this->importer->import(['file' => $this->sourceFile], $this->targetDir, 'blog');
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
