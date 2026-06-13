<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Import;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\Ghost\GhostContentImporter;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class GhostContentImporterTest extends TestCase
{
    private string $sourceFile;
    private string $targetDir;

    protected function setUp(): void
    {
        $sourceDir = sys_get_temp_dir() . '/yiipress-ghost-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-ghost-target-' . uniqid();
        mkdir($sourceDir, 0o755, true);
        mkdir($this->targetDir, 0o755, true);
        $this->sourceFile = $sourceDir . '/ghost.json';
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->sourceFile));
        $this->removeDir($this->targetDir);
    }

    public function testImportsPostsAndPagesFromGhostExport(): void
    {
        file_put_contents($this->sourceFile, json_encode([
            'db' => [[
                'data' => [
                    'posts' => [
                        [
                            'id' => 'post-1',
                            'title' => 'Hello: Ghost',
                            'slug' => 'hello-ghost',
                            'status' => 'published',
                            'type' => 'post',
                            'published_at' => '2024-03-15 10:30:00',
                            'custom_excerpt' => 'Short summary.',
                            'feature_image' => '__GHOST_URL__/content/images/hero.jpg',
                            'html' => '<p>Hello from Ghost.</p>',
                        ],
                        [
                            'id' => 'page-1',
                            'title' => 'About',
                            'slug' => 'about',
                            'status' => 'published',
                            'type' => 'page',
                            'published_at' => '2024-03-16 11:00:00',
                            'html' => '<p>About page.</p>',
                        ],
                    ],
                    'tags' => [
                        ['id' => 'tag-1', 'slug' => 'php', 'name' => 'PHP'],
                    ],
                    'posts_tags' => [
                        ['post_id' => 'post-1', 'tag_id' => 'tag-1'],
                    ],
                    'users' => [
                        ['id' => 'author-1', 'slug' => 'jane-doe', 'name' => 'Jane Doe'],
                    ],
                    'posts_authors' => [
                        ['post_id' => 'post-1', 'author_id' => 'author-1'],
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));

        $result = (new GhostContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->totalMessages());
        assertSame(2, $result->importedCount());
        assertSame([], $result->warnings());

        $post = file_get_contents($this->targetDir . '/blog/2024-03-15-hello-ghost.md');
        $this->assertNotFalse($post);
        assertStringContainsString('title: "Hello: Ghost"', $post);
        assertStringContainsString('date: 2024-03-15 10:30:00', $post);
        assertStringContainsString('summary: Short summary.', $post);
        assertStringContainsString('image: /content/images/hero.jpg', $post);
        assertStringContainsString("tags:\n  - php\n", $post);
        assertStringContainsString("authors:\n  - jane-doe\n", $post);
        assertStringContainsString('<p>Hello from Ghost.</p>', $post);
        $this->assertFileExists($this->targetDir . '/blog/_collection.yaml');

        $page = file_get_contents($this->targetDir . '/about.md');
        $this->assertNotFalse($page);
        assertStringContainsString('title: About', $page);
        assertStringContainsString('permalink: /about/', $page);
        assertStringContainsString('<p>About page.</p>', $page);
    }

    public function testMarksDraftsAndSkipsUnsupportedPostTypes(): void
    {
        file_put_contents($this->sourceFile, json_encode([
            'data' => [
                'posts' => [
                    [
                        'id' => 'draft-1',
                        'title' => 'Draft Post',
                        'slug' => 'draft-post',
                        'status' => 'draft',
                        'type' => 'post',
                        'published_at' => '2024-04-01 09:00:00',
                        'html' => 'Draft body.',
                    ],
                    [
                        'id' => 'unknown-1',
                        'title' => 'Unknown',
                        'slug' => 'unknown',
                        'status' => 'published',
                        'type' => 'custom',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = (new GhostContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->totalMessages());
        assertSame(1, $result->importedCount());
        assertSame(['unknown-1'], $result->skippedFiles());

        $post = file_get_contents($this->targetDir . '/blog/2024-04-01-draft-post.md');
        $this->assertNotFalse($post);
        assertStringContainsString("draft: true\n", $post);
    }

    public function testDoesNotOverwriteDuplicateSlugs(): void
    {
        file_put_contents($this->sourceFile, json_encode([
            'posts' => [
                [
                    'id' => 'post-1',
                    'title' => 'Duplicate',
                    'slug' => 'duplicate',
                    'status' => 'published',
                    'type' => 'post',
                    'published_at' => '2024-05-01 09:00:00',
                    'html' => 'First.',
                ],
                [
                    'id' => 'post-2',
                    'title' => 'Duplicate',
                    'slug' => 'duplicate',
                    'status' => 'published',
                    'type' => 'post',
                    'published_at' => '2024-05-01 10:00:00',
                    'html' => 'Second.',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = (new GhostContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->importedCount());
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate.md');
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate-2.md');
    }

    public function testWarnsWhenFileIsMissing(): void
    {
        $result = (new GhostContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('file option is required', $result->warnings()[0]);
    }

    public function testWarnsWhenJsonIsInvalid(): void
    {
        file_put_contents($this->sourceFile, '{');

        $result = (new GhostContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('Invalid Ghost JSON', $result->warnings()[0]);
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
