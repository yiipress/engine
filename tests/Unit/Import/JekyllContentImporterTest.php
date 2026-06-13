<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Import;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\Jekyll\JekyllContentImporter;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class JekyllContentImporterTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-jekyll-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-jekyll-target-' . uniqid();
        mkdir($this->sourceDir . '/_posts', 0o755, true);
        mkdir($this->targetDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->targetDir);
    }

    public function testImportsJekyllPosts(): void
    {
        file_put_contents(
            $this->sourceDir . '/_posts/2024-03-15-hello-jekyll.md',
            "---\n"
            . "layout: post\n"
            . "title: \"Hello: Jekyll\"\n"
            . "date: \"2024-03-15 10:30:00\"\n"
            . "tags: [php, yii]\n"
            . "categories: docs guides\n"
            . "permalink: /custom/hello/\n"
            . "---\n\n"
            . "Body text.\n",
        );

        $result = (new JekyllContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        assertSame(1, $result->totalMessages());
        assertSame([], $result->warnings());

        $targetFile = $this->targetDir . '/blog/2024-03-15-hello-jekyll.md';
        $content = file_get_contents($targetFile);
        $this->assertNotFalse($content);
        assertStringContainsString('title: "Hello: Jekyll"', $content);
        assertStringContainsString('date: 2024-03-15 10:30:00', $content);
        assertStringContainsString('permalink: /custom/hello/', $content);
        assertStringContainsString("tags:\n  - php\n  - yii\n", $content);
        assertStringContainsString("categories:\n  - docs\n  - guides\n", $content);
        assertStringContainsString("Body text.\n", $content);
        $this->assertFileExists($this->targetDir . '/blog/_collection.yaml');
    }

    public function testDerivesTitleFromHeadingWhenFrontMatterTitleIsMissing(): void
    {
        file_put_contents(
            $this->sourceDir . '/_posts/2024-04-01-heading-title.markdown',
            "---\n"
            . "tags: php yii\n"
            . "---\n\n"
            . "# Heading Title\n\n"
            . "Body.\n",
        );

        $result = (new JekyllContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        $content = file_get_contents($this->targetDir . '/blog/2024-04-01-heading-title.md');
        $this->assertNotFalse($content);
        assertStringContainsString('title: Heading Title', $content);
        assertStringContainsString("tags:\n  - php\n  - yii\n", $content);
    }

    public function testSkipsUnsupportedPostFilenames(): void
    {
        file_put_contents($this->sourceDir . '/_posts/not-dated.md', "---\ntitle: Bad\n---\n\nBody.\n");

        $result = (new JekyllContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->skippedFiles());
        assertCount(1, $result->warnings());
    }

    public function testWarnsWhenPostsDirectoryIsMissing(): void
    {
        $this->removeDir($this->sourceDir . '/_posts');

        $result = (new JekyllContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('_posts directory not found', $result->warnings()[0]);
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
