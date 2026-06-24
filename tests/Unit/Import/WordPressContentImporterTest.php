<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Import;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Import\WordPress\WordPressContentImporter;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class WordPressContentImporterTest extends TestCase
{
    private string $sourceFile;
    private string $targetDir;

    protected function setUp(): void
    {
        $sourceDir = sys_get_temp_dir() . '/yiipress-wordpress-source-' . uniqid();
        $this->targetDir = sys_get_temp_dir() . '/yiipress-wordpress-target-' . uniqid();
        mkdir($sourceDir, 0o755, true);
        mkdir($this->targetDir, 0o755, true);
        $this->sourceFile = $sourceDir . '/wordpress.xml';
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->sourceFile));
        $this->removeDir($this->targetDir);
    }

    public function testImportsPublishedPostAndPageFromWxr(): void
    {
        file_put_contents($this->sourceFile, $this->wxr([
            $this->item([
                'id' => 10,
                'title' => 'Hello: WordPress',
                'link' => 'https://example.com/2024/03/hello-wordpress/',
                'pubDate' => 'Fri, 15 Mar 2024 10:30:00 +0000',
                'postDate' => '2024-03-15 10:30:00',
                'postName' => 'hello-wordpress',
                'status' => 'publish',
                'type' => 'post',
                'content' => '<p>Hello from WordPress.</p>',
                'excerpt' => '<p>Short summary.</p>',
                'categories' => [
                    ['domain' => 'category', 'nicename' => 'docs', 'title' => 'Docs'],
                    ['domain' => 'post_tag', 'nicename' => 'yii', 'title' => 'Yii'],
                ],
            ]),
            $this->item([
                'id' => 11,
                'title' => 'About',
                'link' => 'https://example.com/about/',
                'postDate' => '2024-03-16 11:00:00',
                'postName' => 'about',
                'status' => 'publish',
                'type' => 'page',
                'content' => '<p>About page.</p>',
            ]),
        ]));

        $result = (new WordPressContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->totalMessages());
        assertSame(2, $result->importedCount());
        assertSame([], $result->warnings());

        $post = file_get_contents($this->targetDir . '/blog/2024-03-15-hello-wordpress.md');
        $this->assertNotFalse($post);
        assertStringContainsString('title: "Hello: WordPress"', $post);
        assertStringContainsString('date: 2024-03-15 10:30:00', $post);
        assertStringContainsString('permalink: /2024/03/hello-wordpress/', $post);
        assertStringContainsString('summary: Short summary.', $post);
        assertStringContainsString("tags:\n  - yii\n", $post);
        assertStringContainsString("categories:\n  - docs\n", $post);
        assertStringContainsString('<p>Hello from WordPress.</p>', $post);
        $this->assertFileExists($this->targetDir . '/blog/_collection.yaml');

        $page = file_get_contents($this->targetDir . '/about.md');
        $this->assertNotFalse($page);
        assertStringContainsString('title: About', $page);
        assertStringContainsString('permalink: /about/', $page);
        assertStringContainsString('<p>About page.</p>', $page);
    }

    public function testMarksNonPublishedPostsAsDraftsAndSkipsAttachments(): void
    {
        file_put_contents($this->sourceFile, $this->wxr([
            $this->item([
                'id' => 20,
                'title' => 'Draft Post',
                'postDate' => '2024-04-01 09:00:00',
                'postName' => 'draft-post',
                'status' => 'draft',
                'type' => 'post',
                'content' => 'Draft body.',
            ]),
            $this->item([
                'id' => 21,
                'title' => 'Attachment',
                'status' => 'inherit',
                'type' => 'attachment',
            ]),
        ]));

        $result = (new WordPressContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->totalMessages());
        assertSame(1, $result->importedCount());
        assertSame(['21'], $result->skippedFiles());

        $post = file_get_contents($this->targetDir . '/blog/2024-04-01-draft-post.md');
        $this->assertNotFalse($post);
        assertStringContainsString("draft: true\n", $post);
    }

    public function testDoesNotOverwriteDuplicateSlugs(): void
    {
        file_put_contents($this->sourceFile, $this->wxr([
            $this->item([
                'id' => 30,
                'title' => 'Duplicate',
                'postDate' => '2024-05-01 09:00:00',
                'postName' => 'duplicate',
                'status' => 'publish',
                'type' => 'post',
                'content' => 'First.',
            ]),
            $this->item([
                'id' => 31,
                'title' => 'Duplicate',
                'postDate' => '2024-05-01 10:00:00',
                'postName' => 'duplicate',
                'status' => 'publish',
                'type' => 'post',
                'content' => 'Second.',
            ]),
        ]));

        $result = (new WordPressContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(2, $result->importedCount());
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate.md');
        $this->assertFileExists($this->targetDir . '/blog/2024-05-01-duplicate-2.md');
    }

    public function testWarnsWhenFileIsMissing(): void
    {
        $result = (new WordPressContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('file option is required', $result->warnings()[0]);
    }

    public function testWarnsWhenXmlIsInvalid(): void
    {
        file_put_contents($this->sourceFile, '<rss>');

        $result = (new WordPressContentImporter())->import(['file' => $this->sourceFile], $this->targetDir, 'blog');

        assertSame(0, $result->importedCount());
        assertCount(1, $result->warnings());
        assertStringContainsString('Invalid WordPress WXR XML', $result->warnings()[0]);
    }

    /**
     * @param list<string> $items
     */
    private function wxr(array $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<rss version="2.0"'
            . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
            . ' xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"'
            . ' xmlns:wp="http://wordpress.org/export/1.2/">'
            . '<channel>' . implode('', $items) . '</channel></rss>';
    }

    /**
     * @param array{
     *     id: int,
     *     title: string,
     *     link?: string,
     *     pubDate?: string,
     *     postDate?: string,
     *     postName?: string,
     *     status: string,
     *     type: string,
     *     content?: string,
     *     excerpt?: string,
     *     categories?: list<array{domain: string, nicename: string, title: string}>
     * } $data
     */
    private function item(array $data): string
    {
        $categories = '';
        foreach ($data['categories'] ?? [] as $category) {
            $categories .= '<category domain="' . $category['domain'] . '" nicename="' . $category['nicename'] . '">'
                . '<![CDATA[' . $category['title'] . ']]></category>';
        }

        return '<item>'
            . '<title><![CDATA[' . $data['title'] . ']]></title>'
            . '<link>' . ($data['link'] ?? '') . '</link>'
            . '<pubDate>' . ($data['pubDate'] ?? '') . '</pubDate>'
            . '<content:encoded><![CDATA[' . ($data['content'] ?? '') . ']]></content:encoded>'
            . '<excerpt:encoded><![CDATA[' . ($data['excerpt'] ?? '') . ']]></excerpt:encoded>'
            . '<wp:post_id>' . $data['id'] . '</wp:post_id>'
            . '<wp:post_date>' . ($data['postDate'] ?? '') . '</wp:post_date>'
            . '<wp:post_name><![CDATA[' . ($data['postName'] ?? '') . ']]></wp:post_name>'
            . '<wp:status>' . $data['status'] . '</wp:status>'
            . '<wp:post_type>' . $data['type'] . '</wp:post_type>'
            . $categories
            . '</item>';
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
