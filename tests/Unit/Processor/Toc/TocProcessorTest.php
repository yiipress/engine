<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor\Toc;

use App\Content\Model\Entry;
use App\Processor\Toc\TocProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class TocProcessorTest extends TestCase
{
    private TocProcessor $processor;
    private Entry $entry;

    protected function setUp(): void
    {
        $this->processor = new TocProcessor();
        $this->entry = $this->createEntry();
    }

    public function testInjectsIdIntoHeadings(): void
    {
        $html = '<h2>Hello World</h2>';
        $result = $this->processor->process($html, $this->entry);

        assertStringContainsString('id="hello-world"', $result);
    }

    public function testAllHeadingLevelsAreProcessed(): void
    {
        $html = '<h1>One</h1><h2>Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5><h6>Six</h6>';
        $this->processor->process($html, $this->entry);
        $toc = $this->processor->getToc();

        assertCount(6, $toc);
        assertSame(1, $toc[0]['level']);
        assertSame(6, $toc[5]['level']);
    }

    public function testSlugifiesTextWithSpecialChars(): void
    {
        $html = '<h2>Hello, World! This is a Test.</h2>';
        $this->processor->process($html, $this->entry);
        $toc = $this->processor->getToc();

        assertSame('hello-world-this-is-a-test', $toc[0]['id']);
    }

    public function testDuplicateHeadingsGetNumericSuffix(): void
    {
        $html = '<h2>Intro</h2><h2>Intro</h2><h2>Intro</h2>';
        $result = $this->processor->process($html, $this->entry);

        assertStringContainsString('id="intro"', $result);
        assertStringContainsString('id="intro-2"', $result);
        assertStringContainsString('id="intro-3"', $result);
    }

    public function testTocContainsCorrectEntries(): void
    {
        $html = '<h2>Getting Started</h2><h3>Installation</h3>';
        $this->processor->process($html, $this->entry);
        $toc = $this->processor->getToc();

        assertCount(2, $toc);
        assertSame('getting-started', $toc[0]['id']);
        assertSame('Getting Started', $toc[0]['text']);
        assertSame(2, $toc[0]['level']);
        assertSame('installation', $toc[1]['id']);
        assertSame('Installation', $toc[1]['text']);
        assertSame(3, $toc[1]['level']);
    }

    public function testStripsInnerHtmlFromText(): void
    {
        $html = '<h2><strong>Bold</strong> heading with <code>code</code></h2>';
        $this->processor->process($html, $this->entry);
        $toc = $this->processor->getToc();

        assertSame('Bold heading with code', $toc[0]['text']);
        assertSame('bold-heading-with-code', $toc[0]['id']);
    }

    public function testDoesNotOverwriteExistingId(): void
    {
        $html = '<h2 id="custom-id">My Heading</h2>';
        $result = $this->processor->process($html, $this->entry);

        assertStringContainsString('id="custom-id"', $result);
        assertStringNotContainsString('id="my-heading"', $result);
    }

    public function testCollectsExistingIdIntoToc(): void
    {
        $html = '<h2 id="custom-id">My Heading</h2>';
        $this->processor->process($html, $this->entry);
        $toc = $this->processor->getToc();

        assertCount(1, $toc);
        assertSame('custom-id', $toc[0]['id']);
    }

    public function testTocResetsBetweenProcessCalls(): void
    {
        $this->processor->process('<h2>First</h2>', $this->entry);
        $this->processor->process('<h2>Second</h2>', $this->entry);
        $toc = $this->processor->getToc();

        assertCount(1, $toc);
        assertSame('Second', $toc[0]['text']);
    }

    public function testEmptyContentGivesEmptyToc(): void
    {
        $this->processor->process('<p>No headings here.</p>', $this->entry);

        assertSame([], $this->processor->getToc());
    }

    public function testPreservesHeadingInnerHtmlInOutput(): void
    {
        $html = '<h2><strong>Bold</strong> text</h2>';
        $result = $this->processor->process($html, $this->entry);

        assertStringContainsString('<strong>Bold</strong> text', $result);
    }

    private function createEntry(): Entry
    {
        $file = tempnam(sys_get_temp_dir(), 'yiipress-toc-');
        file_put_contents($file, "---\ntitle: Test\n---\n\nBody.\n");

        return new Entry(
            filePath: $file,
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
            bodyLength: 5,
        );
    }
}
