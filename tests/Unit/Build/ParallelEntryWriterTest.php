<?php

declare(strict_types=1);

namespace App\Tests\Unit\Build;

use App\Build\ParallelEntryWriter;
use App\Build\TemplateResolver;
use App\Build\Theme;
use App\Build\ThemeRegistry;
use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Processor\ContentProcessorInterface;
use App\Processor\ContentProcessorPipeline;
use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function sys_get_temp_dir;

final class ParallelEntryWriterTest extends TestCase
{
    private string $tempDir;
    private string $contentDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yiipress_parallel_writer_test_' . uniqid();
        $this->contentDir = $this->tempDir . '/content';
        $this->outputDir = $this->tempDir . '/output';

        mkdir($this->contentDir . '/blog', 0o755, true);
        mkdir($this->outputDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
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

        rmdir($this->tempDir);
    }

    public function testWriteWithMultipleWorkersWritesAllEntries(): void
    {
        $tasks = [];
        for ($i = 0; $i < 4; $i++) {
            $entry = $this->createEntry("entry-$i", "Post $i");
            $tasks[] = [
                'entry' => $entry,
                'filePath' => $this->outputDir . '/blog/entry-' . $i . '/index.html',
                'permalink' => '/blog/entry-' . $i . '/',
            ];
        }

        $writer = new ParallelEntryWriter($this->createPipeline(), $this->createTemplateResolver());
        $written = $writer->write($this->createSiteConfig(), $tasks, $this->contentDir, 2);

        assertSame(4, $written);
        foreach ($tasks as $index => $task) {
            assertFileExists($task['filePath']);
            assertStringContainsString('Post ' . $index, (string) file_get_contents($task['filePath']));
        }
    }

    public function testWriteFallsBackToSequentialForTinyTaskSet(): void
    {
        $entry = $this->createEntry('entry-0', 'Only Post');
        $tasks = [[
            'entry' => $entry,
            'filePath' => $this->outputDir . '/blog/entry-0/index.html',
            'permalink' => '/blog/entry-0/',
        ]];

        $writer = new ParallelEntryWriter($this->createPipeline(), $this->createTemplateResolver());
        $written = $writer->write($this->createSiteConfig(), $tasks, $this->contentDir, 8);

        assertSame(1, $written);
        assertFileExists($tasks[0]['filePath']);
        assertStringContainsString('Only Post', (string) file_get_contents($tasks[0]['filePath']));
    }

    private function createPipeline(): ContentProcessorPipeline
    {
        return new ContentProcessorPipeline(
            new class () implements ContentProcessorInterface {
                public function process(string $content, Entry $entry): string
                {
                    return '<p>' . $content . '</p>';
                }
            },
        );
    }

    private function createTemplateResolver(): TemplateResolver
    {
        $themePath = $this->tempDir . '/theme';
        mkdir($themePath, 0o755, true);
        file_put_contents($themePath . '/entry.php', <<<'PHP'
<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html>
<head><title><?= $entryTitle ?></title></head>
<body><?= $content ?></body>
</html>
PHP);

        $registry = new ThemeRegistry();
        $registry->register(new Theme('test', $themePath));

        return new TemplateResolver($registry);
    }

    private function createSiteConfig(): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: '',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'utf-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:slug/',
            taxonomies: [],
            params: [],
            theme: 'test',
        );
    }

    private function createEntry(string $slug, string $title): Entry
    {
        $filePath = $this->contentDir . '/blog/' . $slug . '.md';
        $body = "Body for $title";
        file_put_contents($filePath, $body);

        return new Entry(
            filePath: $filePath,
            collection: 'blog',
            slug: $slug,
            title: $title,
            date: new DateTimeImmutable('2024-01-01'),
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: '',
            permalink: '/blog/' . $slug . '/',
            layout: '',
            theme: '',
            weight: 0,
            language: 'en',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: strlen($body),
        );
    }
}
