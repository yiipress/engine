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
