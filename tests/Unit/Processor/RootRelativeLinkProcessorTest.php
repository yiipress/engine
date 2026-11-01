<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\RootRelativeLinkProcessor;

use function PHPUnit\Framework\assertSame;

final class RootRelativeLinkProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function linkProvider(): iterable
    {
        yield 'href in nested page' => [
            '<p><a href="/withdrawn/">x</a></p>',
            '../../',
            '<p><a href="../../withdrawn/">x</a></p>',
        ];
        yield 'href in top-level page' => [
            '<a href="/withdrawn/">x</a>',
            './',
            '<a href="./withdrawn/">x</a>',
        ];
        yield 'site root' => [
            '<a href="/">home</a>',
            '../',
            '<a href="../">home</a>',
        ];
        yield 'src with single quotes' => [
            "<img src='/blog/assets/a.png' alt=\"\">",
            '../',
            "<img src='../blog/assets/a.png' alt=\"\">",
        ];
        yield 'query and fragment kept' => [
            '<a href="/docs/?q=1#part">x</a>',
            '../',
            '<a href="../docs/?q=1#part">x</a>',
        ];
        yield 'uppercase attribute' => [
            '<a HREF="/withdrawn/">x</a>',
            '../',
            '<a HREF="../withdrawn/">x</a>',
        ];
        yield 'whitespace before equals' => [
            '<a href ="/withdrawn/">x</a>',
            '../',
            '<a href ="../withdrawn/">x</a>',
        ];
        yield 'whitespace after equals' => [
            '<img src= "/assets/logo.svg">',
            '../',
            '<img src= "../assets/logo.svg">',
        ];
        yield 'whitespace around equals' => [
            "<a href = '/withdrawn/'>x</a>",
            'https://example.com/site/',
            "<a href = 'https://example.com/site/withdrawn/'>x</a>",
        ];
        yield 'line breaks and tabs around equals' => [
            "<img SRC\t\n=\r\n\f\t\"/assets/logo.svg\">",
            '../../',
            "<img SRC\t\n=\r\n\f\t\"../../assets/logo.svg\">",
        ];
        yield 'unquoted href' => [
            '<a href=/about/>about</a>',
            '../',
            '<a href=../about/>about</a>',
        ];
        yield 'unquoted src with whitespace and following attribute' => [
            '<img SRC = /assets/logo.svg alt="logo">',
            '../../',
            '<img SRC = ../../assets/logo.svg alt="logo">',
        ];
        yield 'unquoted site root in feed' => [
            '<a href=/>home</a>',
            'https://example.com/site/',
            '<a href=https://example.com/site/>home</a>',
        ];
        yield 'unquoted query and fragment' => [
            "<a href=/docs/?q=1#part\tclass=link>docs</a>",
            '../',
            "<a href=../docs/?q=1#part\tclass=link>docs</a>",
        ];
        yield 'greater-than sign in preceding quoted attribute' => [
            '<a title="a > b" href="/about/">about</a>',
            '../',
            '<a title="a > b" href="../about/">about</a>',
        ];
        yield 'href text inside another attribute' => [
            '<a title="example href=/other/" href=/about/>about</a>',
            '../',
            '<a title="example href=/other/" href=../about/>about</a>',
        ];
        yield 'script source with untouched script body' => [
            '<script src=/app.js>const sample = "<a href=/about/>";</script>',
            '../',
            '<script src=../app.js>const sample = "<a href=/about/>";</script>',
        ];
        yield 'multiple attributes on one tag' => [
            '<a href=/about/ src="/image.png">about</a>',
            '../',
            '<a href=../about/ src="../image.png">about</a>',
        ];
    }

    #[DataProvider('linkProvider')]
    public function testRebasesRootRelativeLinks(string $html, string $rootPath, string $expected): void
    {
        $processor = new RootRelativeLinkProcessor();
        $processor->applyRootPath($rootPath);

        assertSame($expected, $processor->process($html, $this->createEntry()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untouchedProvider(): iterable
    {
        yield 'protocol-relative' => ['<a href="//cdn.example.com/x.js">x</a>'];
        yield 'absolute' => ['<a href="https://example.com/withdrawn/">x</a>'];
        yield 'fragment' => ['<a href="#part">x</a>'];
        yield 'mailto' => ['<a href="mailto:a@example.com">x</a>'];
        yield 'relative' => ['<a href="../other/">x</a>'];
        yield 'escaped code' => ['<pre><code>&lt;a href=&quot;/withdrawn/&quot;&gt;</code></pre>'];
        yield 'code text' => ['<code>href="/withdrawn/"</code>'];
        yield 'data attribute' => ['<div data-href="/withdrawn/"></div>'];
        yield 'plain text' => ['<p>Go to /withdrawn/ now</p>'];
        yield 'spaced protocol-relative' => ['<img src = "//cdn.example.com/x.png">'];
        yield 'spaced absolute' => ['<a href = "https://example.com/withdrawn/">x</a>'];
        yield 'spaced data attribute' => ['<div data-href = "/withdrawn/"></div>'];
        yield 'unquoted protocol-relative' => ['<img src = //cdn.example.com/x.png>'];
        yield 'unquoted absolute' => ['<a href=https://example.com/about/>about</a>'];
        yield 'unquoted relative' => ['<a href=../about/>about</a>'];
        yield 'unquoted data attribute' => ['<div data-href=/about/></div>'];
        yield 'unquoted code text' => ['<code>href=/about/</code>'];
        yield 'script text' => ['<script>const sample = "<a href=/about/>";</script>'];
        yield 'style text' => ['<style>.sample::after { content: "<a href=/about/>"; }</style>'];
        yield 'comment text' => ['<!-- <a href=/about/> -->'];
        yield 'textarea text' => ['<textarea><a href=/about/></textarea>'];
        yield 'unquoted data attribute and plain text' => ['<p data-href=/about/>src=/image.png</p>'];
    }

    #[DataProvider('untouchedProvider')]
    public function testLeavesOtherUrlsUnchanged(string $html): void
    {
        $processor = new RootRelativeLinkProcessor();
        $processor->applyRootPath('../');

        assertSame($html, $processor->process($html, $this->createEntry()));
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
