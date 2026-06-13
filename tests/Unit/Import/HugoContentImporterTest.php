<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Import;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\Hugo\HugoContentImporter;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class HugoContentImporterTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-hugo-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-hugo-target-' . uniqid();
        mkdir($this->sourceDir . '/content/posts', 0o755, true);
        mkdir($this->targetDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->targetDir);
    }

    public function testImportsTomlFrontMatterPosts(): void
    {
        file_put_contents(
            $this->sourceDir . '/content/posts/hello-hugo.md',
            "+++\n"
            . "title = \"Hello: Hugo\"\n"
            . "date = \"2024-03-15T10:30:00Z\"\n"
            . "tags = [\"php\", \"yii\"]\n"
            . "categories = [\"docs\", \"guides\"]\n"
            . "url = \"/custom/hello/\"\n"
            . "draft = true\n"
            . "+++\n\n"
            . "Body text.\n",
        );

        $result = (new HugoContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        assertSame(1, $result->totalMessages());
        assertSame([], $result->warnings());

        $targetFile = $this->targetDir . '/blog/2024-03-15-hello-hugo.md';
        $content = file_get_contents($targetFile);
        $this->assertNotFalse($content);
        assertStringContainsString('title: "Hello: Hugo"', $content);
        assertStringContainsString('date: 2024-03-15T10:30:00Z', $content);
        assertStringContainsString('permalink: /custom/hello/', $content);
        assertStringContainsString("draft: true\n", $content);
        assertStringContainsString("tags:\n  - php\n  - yii\n", $content);
        assertStringContainsString("categories:\n  - docs\n  - guides\n", $content);
        assertStringContainsString("Body text.\n", $content);
        $this->assertFileExists($this->targetDir . '/blog/_collection.yaml');
    }

    public function testImportsYamlFrontMatterPostsAndDerivesDateFromFilename(): void
    {
        file_put_contents(
            $this->sourceDir . '/content/posts/2024-04-01-heading-title.md',
            "---\n"
            . "tags: php yii\n"
            . "---\n\n"
            . "# Heading Title\n\n"
            . "Body.\n",
        );

        $result = (new HugoContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        $content = file_get_contents($this->targetDir . '/blog/2024-04-01-heading-title.md');
        $this->assertNotFalse($content);
        assertStringContainsString('title: Heading Title', $content);
        assertStringContainsString('date: 2024-04-01', $content);
        assertStringContainsString("tags:\n  - php\n  - yii\n", $content);
    }

    public function testWarnsWhenContentDirectoryIsMissing(): void
    {
        $this->removeDir($this->sourceDir . '/content');

        $result = (new HugoContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('content directory not found', $result->warnings()[0]);
    }

    public function testNormalizesFrontMatterSlugForFilesystem(): void
    {
        file_put_contents(
            $this->sourceDir . '/content/posts/unsafe.md',
            "---\n"
            . "title: Unsafe\n"
            . "slug: ../../outside\n"
            . "---\n\n"
            . "Body.\n",
        );

        $result = (new HugoContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        $this->assertFileExists($this->targetDir . '/blog/outside.md');
        $this->assertFileDoesNotExist($this->targetDir . '/outside.md');
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
