<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Build;

use YiiPress\Build\LlmsTxtGenerator;
use YiiPress\Content\Model\Collection;
use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function PHPUnit\Framework\assertFileDoesNotExist;
use function PHPUnit\Framework\assertFileExists;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;

final class LlmsTxtGeneratorTest extends TestCase
{
    private string $outputDir;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/yiipress-llms-test-' . uniqid();
        mkdir($this->outputDir, 0o755, true);

        $this->tempFile = sys_get_temp_dir() . '/yiipress-llms-body-' . uniqid() . '.md';
        file_put_contents($this->tempFile, "Body content.\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        if (is_dir($this->outputDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->outputDir, FilesystemIterator::SKIP_DOTS),
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
            rmdir($this->outputDir);
        }
    }

    public function testGeneratesLlmsTxtFile(): void
    {
        $generator = new LlmsTxtGenerator();
        $content = $generator->generate($this->createSiteConfig(), [], [], $this->outputDir);

        assertFileExists($this->outputDir . '/llms.txt');
        assertSame($content, file_get_contents($this->outputDir . '/llms.txt'));
        assertStringContainsString("# Test Site\n\nA test site\n", $content);
    }

    public function testDoesNotGenerateWhenDisabled(): void
    {
        $generator = new LlmsTxtGenerator();
        $content = $generator->generate($this->createSiteConfig(llmsTxt: false), [], [], $this->outputDir);

        assertSame('', $content);
        assertFileDoesNotExist($this->outputDir . '/llms.txt');
    }

    public function testIncludesCollectionEntriesAndStandalonePages(): void
    {
        $generator = new LlmsTxtGenerator();
        $collection = $this->createCollection();
        $entry = $this->createEntry(title: 'Hello World', slug: 'hello-world', summary: 'Intro text.');
        $page = $this->createEntry(title: 'About Us', slug: 'about', permalink: '/about/', summary: 'About the project.');

        $content = $generator->generate(
            $this->createSiteConfig(),
            ['blog' => $collection],
            ['blog' => [$entry]],
            $this->outputDir,
            [$page],
            noWrite: true,
        );

        assertStringContainsString("## Blog\n- [Hello World](https://example.com/blog/hello-world/): Intro text.", $content);
        assertStringContainsString("## Pages\n- [About Us](https://example.com/about/): About the project.", $content);
        assertFileDoesNotExist($this->outputDir . '/llms.txt');
    }

    public function testEscapesMarkdownLinkText(): void
    {
        $generator = new LlmsTxtGenerator();
        $collection = $this->createCollection();
        $entry = $this->createEntry(title: 'A [bracketed] \\ title', slug: 'bracketed');

        $content = $generator->generate(
            $this->createSiteConfig(),
            ['blog' => $collection],
            ['blog' => [$entry]],
            $this->outputDir,
            noWrite: true,
        );

        assertStringContainsString('- [A \[bracketed\] \\\\ title](https://example.com/blog/bracketed/)', $content);
    }

    private function createSiteConfig(bool $llmsTxt = true): SiteConfig
    {
        return new SiteConfig(
            title: 'Test Site',
            description: 'A test site',
            baseUrl: 'https://example.com',
            defaultLanguage: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            llmsTxt: $llmsTxt,
        );
    }

    private function createCollection(): Collection
    {
        return new Collection(
            name: 'blog',
            title: 'Blog',
            description: '',
            permalink: '/:collection/:slug/',
            sortBy: 'date',
            sortOrder: 'desc',
            entriesPerPage: 10,
            feed: true,
            listing: true,
        );
    }

    private function createEntry(
        string $title,
        string $slug,
        string $summary = '',
        string $permalink = '',
    ): Entry {
        return new Entry(
            filePath: $this->tempFile,
            collection: 'blog',
            slug: $slug,
            title: $title,
            date: null,
            draft: false,
            tags: [],
            categories: [],
            authors: [],
            summary: $summary,
            permalink: $permalink,
            layout: '',
            theme: '',
            weight: 0,
            language: '',
            redirectTo: '',
            extra: [],
            bodyOffset: 0,
            bodyLength: (int) filesize($this->tempFile),
        );
    }
}
