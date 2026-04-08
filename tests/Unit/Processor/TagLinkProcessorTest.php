<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\TagLinkProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class TagLinkProcessorTest extends TestCase
{
    public function testConvertsHashtagsToLinks(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>Check out #yii3 and #php for more info.</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p>Check out <a href="/tags/yii3/" class="tag-link">#yii3</a> and <a href="/tags/php/" class="tag-link">#php</a> for more info.</p>',
            $result
        );
    }

    public function testPreservesMixedCaseInDisplay(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>#YiiFramework #PHPUnit</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/tags/yiiframework/" class="tag-link">#YiiFramework</a> <a href="/tags/phpunit/" class="tag-link">#PHPUnit</a></p>',
            $result
        );
    }

    public function testDoesNotConvertHashtagsInCodeBlocks(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>Example: <code>#hashtag</code> should not be converted.</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p>Example: <code>#hashtag</code> should not be converted.</p>',
            $result
        );
    }

    public function testDoesNotConvertHashtagsInPreBlocks(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<pre>#hashtag in code</pre><p>#hashtag outside</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<pre>#hashtag in code</pre><p><a href="/tags/hashtag/" class="tag-link">#hashtag</a> outside</p>',
            $result
        );
    }

    public function testDoesNotConvertHashtagsInLinks(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p><a href="/test">#link</a> and #standalone</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/test">#link</a> and <a href="/tags/standalone/" class="tag-link">#standalone</a></p>',
            $result
        );
    }

    public function testHandlesHashtagsWithHyphens(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>#multi-word-tag and #another-tag here</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/tags/multi-word-tag/" class="tag-link">#multi-word-tag</a> and <a href="/tags/another-tag/" class="tag-link">#another-tag</a> here</p>',
            $result
        );
    }

    public function testHandlesHashtagsWithUnderscores(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>#yii_framework and #php_8</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/tags/yii_framework/" class="tag-link">#yii_framework</a> and <a href="/tags/php_8/" class="tag-link">#php_8</a></p>',
            $result
        );
    }

    public function testHandlesHashtagsWithNumbers(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>#php8 and #yii3</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/tags/php8/" class="tag-link">#php8</a> and <a href="/tags/yii3/" class="tag-link">#yii3</a></p>',
            $result
        );
    }

    public function testDoesNotConvertStandaloneHash(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>Use # for comments</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p>Use # for comments</p>',
            $result
        );
    }

    public function testHandlesMultipleHashtagsInSameLine(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>#yii #php #framework</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/tags/yii/" class="tag-link">#yii</a> <a href="/tags/php/" class="tag-link">#php</a> <a href="/tags/framework/" class="tag-link">#framework</a></p>',
            $result
        );
    }

    public function testHandlesEmptyContent(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '';

        $result = $processor->process($content, $this->createEntry());

        assertSame('', $result);
    }

    public function testSkipsRegexWorkWhenContentHasNoHashCharacter(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p>Plain rendered HTML without hashtags.</p>';

        assertSame($content, $processor->process($content, $this->createEntry()));
    }

    public function testUsesCustomRootPath(): void
    {
        $processor = new TagLinkProcessor('/blog/');
        $content = '<p>#test</p>';

        $result = $processor->process($content, $this->createEntry());

        assertSame(
            '<p><a href="/blog/tags/test/" class="tag-link">#test</a></p>',
            $result
        );
    }

    public function testDoesNotConvertColorCodesInHtmlAttributes(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '$<strong style="color: #f00">2y</strong>$<strong style="color: #0a0">13</strong>$<strong style="color: #00f">YUUgrko03UmNU/fe6gNcO.</strong><strong style="color: #00a">Hka4lrdRlkq0iJ5d4bv4fK.sKS.6jXu</strong>';

        $result = $processor->process($content, $this->createEntry());

        // Color codes in HTML attributes should NOT be converted to tag links
        assertSame($content, $result);
    }

    public function testConvertsHashtagsOutsideHtmlAttributes(): void
    {
        $processor = new TagLinkProcessor('/');
        $content = '<p style="color: #fff">This is white text with #realhashtag</p>';

        $result = $processor->process($content, $this->createEntry());

        // Color code in attribute preserved, real hashtag converted
        assertSame(
            '<p style="color: #fff">This is white text with <a href="/tags/realhashtag/" class="tag-link">#realhashtag</a></p>',
            $result
        );
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_taglink_test_');
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

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
