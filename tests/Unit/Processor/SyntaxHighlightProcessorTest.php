<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Content\Model\SiteConfig;
use YiiPress\Highlighter;
use YiiPress\Processor\SyntaxHighlightProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertNotFalse;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function class_exists;

final class SyntaxHighlightProcessorTest extends TestCase
{
    public function testSkipsHighlightingWhenRenderedHtmlHasNoLanguageCodeBlock(): void
    {
        $processor = new SyntaxHighlightProcessor(new Highlighter());
        $content = '<p>Regular rendered HTML without code blocks.</p>';

        assertSame($content, $processor->process($content, $this->createEntry()));
    }

    public function testUsesConfiguredHighlightTheme(): void
    {
        $html = '<pre><code class="language-php">&lt;?php echo 1;</code></pre>';

        $defaultProcessor = new SyntaxHighlightProcessor(new Highlighter());
        $defaultResult = $defaultProcessor->process($html, $this->createEntry());

        $configuredProcessor = new SyntaxHighlightProcessor(new Highlighter());
        $configuredProcessor->applySiteConfig($this->createSiteConfig('Solarized (dark)'));
        $configuredResult = $configuredProcessor->process($html, $this->createEntry());

        assertNotSame($defaultResult, $configuredResult);
    }

    public function testPreservesNormalizedLanguageMetadataAroundHighlightedBlock(): void
    {
        $html = '<pre><code class="language-PHP">&lt;?php echo 1;</code></pre>';

        $result = (new SyntaxHighlightProcessor(new Highlighter()))->process($html, $this->createEntry());

        assertStringContainsString(
            '<div class="code-block" data-language="php"><span class="code-language-label">PHP</span><pre',
            $result,
        );
        assertStringContainsString('style=', $result);
        assertStringNotContainsString('language-PHP', $result);
    }

    public function testPreservesUnknownLanguageMetadataWhenHighlighterFallsBackToPlainText(): void
    {
        $html = '<pre><code class="language-custom-lang">some code</code></pre>';

        $result = (new SyntaxHighlightProcessor(new Highlighter()))->process($html, $this->createEntry());

        assertStringContainsString('data-language="custom-lang"', $result);
        assertStringContainsString('<span class="code-language-label">CUSTOM-LANG</span>', $result);
        assertStringContainsString('some code', $result);
    }

    public function testLeavesUnlabeledCodeBlockUnwrapped(): void
    {
        $html = '<pre><code>plain code</code></pre>';

        $result = (new SyntaxHighlightProcessor(new Highlighter()))->process($html, $this->createEntry());

        assertSame($html, $result);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_syntax_processor_test_');
        assertNotFalse($tmp);

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
        if (!class_exists(Highlighter::class)) {
            $this->markTestSkipped('YiiPress\\Highlighter is not available.');
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
            defaultLanguage: 'en',
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
