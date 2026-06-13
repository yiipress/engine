<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\LatexMath\LatexMathProcessor;

use function PHPUnit\Framework\assertSame;

final class LatexMathProcessorTest extends TestCase
{
    private LatexMathProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new LatexMathProcessor();
    }

    public function testProcessLeavesRenderedContentUnchanged(): void
    {
        $content = '<p>Inline <x-equation>x</x-equation>.</p>';

        assertSame($content, $this->processor->process($content, $this->createEntry()));
    }

    public function testHeadAssetsReturnsKaTexAssetsWhenMathIsPresent(): void
    {
        $assets = $this->processor->headAssets('<p><x-equation type="display">x</x-equation></p>');

        $this->assertStringContainsString('katex.min.css', $assets);
        $this->assertStringContainsString('katex.min.js', $assets);
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
        $this->assertStringEndsWith('/Processor/LatexMath/assets/latex-math.js', $sourceFile);
        assertSame('assets/plugins/latex-math.js', $files[$sourceFile]);

        $script = file_get_contents($sourceFile);
        $this->assertNotFalse($script);
        $this->assertStringContainsString("document.querySelectorAll('x-equation')", $script);
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
