<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\LatexMath\LatexMathProcessor;

use function PHPUnit\Framework\assertSame;
use function str_replace;

final class LatexMathProcessorTest extends TestCase
{
    private LatexMathProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new LatexMathProcessor();
    }

    public function testProcessLeavesRenderedContentUnchanged(): void
    {
        $content = '<p>Inline <span class="math">x</span>.</p>';

        assertSame($content, $this->processor->process($content, $this->createEntry()));
    }

    public function testHeadAssetsReturnsKaTexAssetsWhenMathIsPresent(): void
    {
        $assets = $this->processor->headAssets('<p><span class="math display">x</span></p>');

        $this->assertStringContainsString('katex.min.css', $assets);
        $this->assertStringContainsString('katex.min.js', $assets);
        $this->assertStringContainsString('sha384-nB0miv6/jRmo5UMMR1wu3Gz6NLsoTkbqJghGIsx//Rlm+ZU03BU6SQNC66uf4l5+', $assets);
        $this->assertStringContainsString('sha384-7zkQWkzuo3B5mTepMUcHkMB5jZaolc2xDwL6VFqjFALcbeS9Ggm/Yr2r3Dy4lfFg', $assets);
        $this->assertStringContainsString('crossorigin="anonymous"', $assets);
        $this->assertStringContainsString('assets/plugins/latex-math.js', $assets);
    }

    public function testHeadAssetsReturnsEmptyStringWhenMathIsAbsent(): void
    {
        assertSame('', $this->processor->headAssets('<p>No math.</p>'));
    }

    public function testAssetFilesReturnsBrowserEnhancer(): void
    {
        $files = $this->processor->assetFiles();

        $this->assertCount(1, $files);
        $sourceFile = array_key_first($files);
        $this->assertNotNull($sourceFile);
        $normalizedSourceFile = str_replace('\\', '/', $sourceFile);
        $this->assertStringEndsWith('/Processor/LatexMath/assets/latex-math.js', $normalizedSourceFile);
        assertSame('assets/plugins/latex-math.js', $files[$sourceFile]);

        $script = file_get_contents($sourceFile);
        $this->assertNotFalse($script);
        $this->assertStringContainsString("document.querySelectorAll('span.math')", $script);
        $this->assertStringContainsString('window.katex.render', $script);
        $this->assertStringContainsString('throwOnError: false', $script);
    }

    private function createEntry(): Entry
    {
        return new Entry(
            filePath: __FILE__,
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
}
