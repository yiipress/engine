<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiPress\Content\Model\ProcessorConfig;
use YiiPress\Content\Parser\InvalidContentConfigException;
use YiiPress\Processor\ProjectProcessorLoader;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;

final class ProjectProcessorLoaderTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/yiipress-project-processors-' . uniqid() . '/content';
        mkdir($this->contentDir . '/processors', 0o755, true);
        file_put_contents($this->contentDir . '/config.yaml', "title: Test\nlanguages: [en]\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir(dirname($this->contentDir));
    }

    public function testLoadsAutoDiscoveredProcessorsForContentAndFeed(): void
    {
        file_put_contents(
            $this->contentDir . '/processors/badge.php',
            '<?php return static fn(string $content): string => $content . " badge";',
        );

        $set = $this->loader()->load(new ProcessorConfig());

        assertCount(1, $set->contentBeforeMarkdown);
        assertCount(1, $set->feedBeforeMarkdown);
    }

    public function testLoadsConfiguredProcessorPaths(): void
    {
        file_put_contents(
            $this->contentDir . '/processors/after.php',
            '<?php return static fn(string $content): string => $content . " after";',
        );

        $set = $this->loader()->load(new ProcessorConfig(
            discover: false,
            contentAfterMarkdown: ['processors/after.php'],
        ));

        assertCount(1, $set->contentAfterMarkdown);
        assertSame('content after', $set->contentAfterMarkdown[0]->process('content', $this->createEntry()));
    }

    public function testRejectsProcessorFilesOutsideContentDirectory(): void
    {
        $outside = sys_get_temp_dir() . '/yiipress-outside-processor-' . uniqid() . '.php';
        file_put_contents($outside, '<?php return static fn(string $content): string => $content;');

        try {
            $this->loader()->load(new ProcessorConfig(discover: false, contentBeforeMarkdown: [$outside]));
            $this->fail('Expected invalid content configuration exception.');
        } catch (InvalidContentConfigException $e) {
            assertSame('Invalid content configuration', $e->getName());
        } finally {
            unlink($outside);
        }
    }

    public function testRejectsInvalidProcessorReturnValue(): void
    {
        file_put_contents($this->contentDir . '/processors/invalid.php', '<?php return "not a processor";');

        $this->expectException(InvalidContentConfigException::class);

        $this->loader()->load(new ProcessorConfig(discover: false, contentBeforeMarkdown: ['processors/invalid.php']));
    }

    private function loader(): ProjectProcessorLoader
    {
        return new ProjectProcessorLoader($this->contentDir, $this->contentDir . '/config.yaml');
    }

    private function createEntry(): \YiiPress\Content\Model\Entry
    {
        return new \YiiPress\Content\Model\Entry(
            filePath: $this->contentDir . '/post.md',
            collection: 'blog',
            slug: 'post',
            title: 'Post',
            date: null,
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
