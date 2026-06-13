<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\Shortcode\ProjectShortcodeProcessor;

use function PHPUnit\Framework\assertSame;

final class ProjectShortcodeProcessorTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-shortcodes-' . uniqid();
        mkdir($this->contentDir . '/blog', 0o755, true);
        mkdir($this->contentDir . '/shortcodes', 0o755, true);
        file_put_contents($this->contentDir . '/config.yaml', "title: Test\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    public function testRendersInlineShortcodeTemplateReturnValue(): void
    {
        file_put_contents(
            $this->contentDir . '/shortcodes/badge.php',
            '<?php return "**" . ($attributes["label"] ?? "") . "**";',
        );

        $result = (new ProjectShortcodeProcessor())->process(
            'Status: {{< badge label="Stable" >}}',
            $this->entry(),
        );

        assertSame('Status: **Stable**', $result);
    }

    public function testRendersBlockShortcodeWithContent(): void
    {
        file_put_contents(
            $this->contentDir . '/shortcodes/callout.php',
            '<?php return "<aside><strong>" . ($attributes["title"] ?? "") . "</strong>" . $content . "</aside>";',
        );

        $result = (new ProjectShortcodeProcessor())->process(
            "{{< callout title='Note' >}}Body **text**.{{< /callout >}}",
            $this->entry(),
        );

        assertSame('<aside><strong>Note</strong>Body **text**.</aside>', $result);
    }

    public function testUsesEchoedTemplateOutput(): void
    {
        file_put_contents(
            $this->contentDir . '/shortcodes/echo.php',
            '<?php echo "<span>" . $name . "</span>";',
        );

        $result = (new ProjectShortcodeProcessor())->process('{{< echo >}}', $this->entry());

        assertSame('<span>echo</span>', $result);
    }

    public function testLeavesUnknownShortcodesUnchanged(): void
    {
        $result = (new ProjectShortcodeProcessor())->process('Hello {{< missing value="1" >}}', $this->entry());

        assertSame('Hello {{< missing value="1" >}}', $result);
    }

    private function entry(): Entry
    {
        $file = $this->contentDir . '/blog/post.md';
        file_put_contents($file, "---\ntitle: Test\n---\nBody.");

        return new Entry(
            filePath: $file,
            collection: 'blog',
            slug: 'post',
            title: 'Post',
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '',
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: 0,
        );
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
