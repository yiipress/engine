<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Highlighter\SyntaxHighlighter;
use App\Processor\SyntaxHighlightProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertNotSame;

final class SyntaxHighlightProcessorTest extends TestCase
{
    public function testSkipsHighlightingWhenRenderedHtmlHasNoLanguageCodeBlock(): void
    {
        $processor = new SyntaxHighlightProcessor(new SyntaxHighlighter());
        $content = '<p>Regular rendered HTML without code blocks.</p>';

        assertSame($content, $processor->process($content, $this->createEntry()));
    }

    public function testUsesConfiguredHighlightTheme(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo 1;</code></pre>';

        $defaultProcessor = new SyntaxHighlightProcessor(new SyntaxHighlighter());
        $defaultResult = $defaultProcessor->process($html, $this->createEntry());

        $configuredProcessor = new SyntaxHighlightProcessor(new SyntaxHighlighter());
        $configuredProcessor->applySiteConfig($this->createSiteConfig('Solarized (dark)'));
        $configuredResult = $configuredProcessor->process($html, $this->createEntry());

        assertNotSame($defaultResult, $configuredResult);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_syntax_processor_test_');
        file_put_contents($tmp, "---\ntitle: Test\n---\nBody.");
        $this->tempFiles[] = $tmp;

        return new Entry(
            filePath: $tmp,
            collection: 'blog',
            slug: 'test',
            title: 'Test',
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

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        if (!extension_loaded('ffi')) {
            $this->markTestSkipped('ext-ffi is not available.');
        }

        if (!file_exists('/usr/local/lib/libyiipress_highlighter.so')) {
            $this->markTestSkipped('libyiipress_highlighter.so is not available.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function createSiteConfig(string $highlightTheme): SiteConfig
    {
        return new SiteConfig(
            title: 'Test',
            description: '',
            baseUrl: '',
            language: 'en',
            charset: 'UTF-8',
            defaultAuthor: '',
            dateFormat: 'Y-m-d',
            entriesPerPage: 10,
            permalink: '/:collection/:slug/',
            taxonomies: [],
            params: [],
            highlightTheme: $highlightTheme,
        );
    }
}
