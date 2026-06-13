<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use YiiPress\Import\WordPress\WordPressContentImporter;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class WordPressImporterBench
{
    private string $sourceDir;
    private string $sourceFile;
    private string $targetDir;
    private WordPressContentImporter $importer;

    public function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-wordpress-bench-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-wordpress-bench-target-' . uniqid();
        mkdir($this->sourceDir, 0o755, true);
        mkdir($this->targetDir, 0o755, true);
        $this->sourceFile = $this->sourceDir . '/wordpress.xml';

        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = '<item>'
                . '<title><![CDATA[Post ' . $i . ']]></title>'
                . '<link>https://example.com/2024/03/post-' . $i . '/</link>'
                . '<content:encoded><![CDATA[<p>Body ' . $i . '.</p>]]></content:encoded>'
                . '<excerpt:encoded><![CDATA[Summary ' . $i . '.]]></excerpt:encoded>'
                . '<wp:post_id>' . $i . '</wp:post_id>'
                . '<wp:post_date>2024-03-15 10:30:00</wp:post_date>'
                . '<wp:post_name>post-' . $i . '</wp:post_name>'
                . '<wp:status>publish</wp:status>'
                . '<wp:post_type>post</wp:post_type>'
                . '<category domain="post_tag" nicename="php"><![CDATA[PHP]]></category>'
                . '<category domain="category" nicename="docs"><![CDATA[Docs]]></category>'
                . '</item>';
        }

        file_put_contents(
            $this->sourceFile,
            '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<rss version="2.0"'
            . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
            . ' xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"'
            . ' xmlns:wp="http://wordpress.org/export/1.2/">'
            . '<channel>' . implode('', $items) . '</channel></rss>',
        );

        $this->importer = new WordPressContentImporter();
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
