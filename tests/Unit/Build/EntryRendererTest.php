<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\EntryRenderer;
use App\Build\TemplateResolver;
use App\Content\Model\Entry;
use App\Content\Model\MarkdownConfig;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorPipeline;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class EntryRendererTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-renderer-test-' . uniqid();
        mkdir($this->contentDir . '/blog', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->contentDir);
    }

    public function testRendersWithDefaultTemplate(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Test Post\n---\n\nHello world.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Test Post');
        $renderer = new EntryRenderer($this->createPipeline(), new TemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('<h1>Test Post</h1>', $html);
        assertStringContainsString('Hello world.', $html);
    }

    public function testRendersWithCustomLayout(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/wide.php', <<<'PHP'
<?php
/** @var string $entryTitle */
/** @var string $content */
?>
<div class="wide-layout"><h1><?= htmlspecialchars($entryTitle) ?></h1><?= $content ?></div>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Wide Post\nlayout: wide\n---\n\nWide content.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Wide Post', layout: 'wide');
        $renderer = new EntryRenderer($this->createPipeline(), new TemplateResolver([$this->contentDir . '/templates']), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('wide-layout', $html);
        assertStringContainsString('<h1>Wide Post</h1>', $html);
        assertStringContainsString('Wide content.', $html);
    }

    public function testFallsBackToDefaultWhenLayoutFileNotFound(): void
    {
        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Missing Layout\nlayout: nonexistent\n---\n\nContent.\n");

        $entry = $this->createEntry(filePath: $entryFile, title: 'Missing Layout', layout: 'nonexistent');
        $renderer = new EntryRenderer($this->createPipeline(), new TemplateResolver(), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('<h1>Missing Layout</h1>', $html);
        assertStringContainsString('Content.', $html);
    }

    public function testCustomLayoutReceivesAllVariables(): void
    {
        mkdir($this->contentDir . '/templates', 0o755, true);
        file_put_contents($this->contentDir . '/templates/full.php', <<<'PHP'
<?php
/** @var string $siteTitle */
/** @var string $entryTitle */
/** @var string $content */
/** @var string $date */
/** @var string $author */
/** @var string $collection */
?>
<div data-site="<?= htmlspecialchars($siteTitle) ?>" data-date="<?= htmlspecialchars($date) ?>" data-author="<?= htmlspecialchars($author) ?>" data-collection="<?= htmlspecialchars($collection) ?>"><?= $content ?></div>
PHP);

        $entryFile = $this->contentDir . '/blog/post.md';
        file_put_contents($entryFile, "---\ntitle: Full Post\nlayout: full\n---\n\nBody.\n");

        $entry = $this->createEntry(
            filePath: $entryFile,
            title: 'Full Post',
            layout: 'full',
            collection: 'blog',
            authors: ['alice'],
        );
        $renderer = new EntryRenderer($this->createPipeline(), new TemplateResolver([$this->contentDir . '/templates']), contentDir: $this->contentDir);
        $html = $renderer->render($this->createSiteConfig(), $entry);

        assertStringContainsString('data-site="Test Site"', $html);
        assertStringContainsString('data-date="2024-01-01"', $html);
        assertStringContainsString('data-author="alice"', $html);
        assertStringContainsString('data-collection="blog"', $html);
    }

    private function createPipeline(): ContentProcessorPipeline
    {
        return new ContentProcessorPipeline();
    }

    private function createSiteConfig(): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: 'https://example.com',
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
        );
    }

    /**
     * @param list<string> $authors
     */
    private function createEntry(
        string $filePath,
        string $title = 'Post',
        string $layout = '',
        string $collection = 'blog',
        array $authors = [],
    ): Entry {
        $content = file_get_contents($filePath);
        $bodyMarker = "---\n\n";
        $bodyPos = strpos($content, $bodyMarker, 4);
        $bodyOffset = $bodyPos !== false ? $bodyPos + strlen($bodyMarker) : 0;
        $bodyLength = strlen($content) - $bodyOffset;

        return new Entry(
            filePath: $filePath,
            collection: $collection,
            slug: 'post',
            title: $title,
            date: new \DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: $authors,
            summary: '',
            permalink: '',
            layout: $layout,
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: $bodyOffset,
            bodyLength: $bodyLength,
        );
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
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
