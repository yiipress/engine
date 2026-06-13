<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Import;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\Medium\MediumContentImporter;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class MediumContentImporterTest extends TestCase
{
    private string $sourceDir;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/yiipress-medium-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-medium-target-' . uniqid();
        mkdir($this->sourceDir . '/posts', 0o755, true);
        mkdir($this->targetDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->sourceDir);
        $this->removeDir($this->targetDir);
    }

    public function testImportsMarkdownWithYamlFrontMatter(): void
    {
        file_put_contents(
            $this->sourceDir . '/posts/hello-medium.md',
            "---\n"
            . "title: \"Hello: Medium\"\n"
            . "date: 2024-03-15T10:30:00Z\n"
            . "canonical_url: https://medium.com/@author/hello-medium\n"
            . "tags: php, yii\n"
            . "categories:\n"
            . "  - docs\n"
            . "draft: true\n"
            . "---\n\n"
            . "Body text.\n",
        );

        $result = (new MediumContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        assertSame(1, $result->totalMessages());
        assertSame([], $result->warnings());

        $content = file_get_contents($this->targetDir . '/blog/2024-03-15-hello-medium.md');
        $this->assertNotFalse($content);
        assertStringContainsString('title: "Hello: Medium"', $content);
        assertStringContainsString('date: 2024-03-15', $content);
        assertStringContainsString('origin: "https://medium.com/@author/hello-medium"', $content);
        assertStringContainsString("draft: true\n", $content);
        assertStringContainsString("tags:\n  - php\n  - yii\n", $content);
        assertStringContainsString("categories:\n  - docs\n", $content);
        assertStringContainsString("Body text.\n", $content);
        $this->assertFileExists($this->targetDir . '/blog/_collection.yaml');
    }

    public function testInfersTitleAndDateFromMarkdown(): void
    {
        file_put_contents(
            $this->sourceDir . '/posts/2024-04-01-heading-title.md',
            "# Heading Title\n\nBody.\n",
        );

        $result = (new MediumContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(1, $result->importedCount());
        $content = file_get_contents($this->targetDir . '/blog/2024-04-01-heading-title.md');
        $this->assertNotFalse($content);
        assertStringContainsString('title: Heading Title', $content);
        assertStringContainsString('date: 2024-04-01', $content);
    }

    public function testDoesNotOverwriteDuplicateSlugs(): void
    {
        file_put_contents($this->sourceDir . '/posts/2024-05-01-duplicate.md', "# First\n");
        file_put_contents(
            $this->sourceDir . '/posts/duplicate-copy.md',
            "---\ndate: 2024-05-01\nslug: duplicate\n---\n# Second\n",
        );

        $result = (new MediumContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(2, $result->importedCount());
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate.md');
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate-2.md');
    }

    public function testWarnsWhenDirectoryIsMissing(): void
    {
        $this->removeDir($this->sourceDir);

        $result = (new MediumContentImporter())->import(['directory' => $this->sourceDir], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('directory option is required', $result->warnings()[0]);
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
