<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\Shortcode\TweetProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class TweetProcessorTest extends TestCase
{
    private TweetProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new TweetProcessor();
    }

    public function testConvertsTweetShortcodeToEmbed(): void
    {
        $result = $this->processor->process('[tweet id="1234567890" /]', $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
        $this->assertStringContainsString('<blockquote class="twitter-tweet"', $result);
        $this->assertStringContainsString('class="shortcode shortcode-tweet"', $result);
    }

    public function testConvertsTweetShortcodeWithoutSelfClosingSlash(): void
    {
        $result = $this->processor->process('[tweet id="1234567890"]', $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testSkipsRegexWorkWhenContentHasNoTweetShortcodeMarker(): void
    {
        $input = 'Plain content without shortcode markers.';

        assertSame($input, $this->processor->process($input, $this->createEntry()));
    }

    public function testEmbedHasDoNotTrackAttribute(): void
    {
        $result = $this->processor->process('[tweet id="1234567890" /]', $this->createEntry());

        $this->assertStringContainsString('data-dnt="true"', $result);
    }

    public function testReturnsUnchangedWhenIdMissing(): void
    {
        $input = '[tweet /]';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame('[tweet /]', $result);
    }

    public function testEscapesHtmlInTweetId(): void
    {
        $result = $this->processor->process('[tweet id="<script>alert(1)</script>" /]', $this->createEntry());

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testCaseInsensitive(): void
    {
        $result = $this->processor->process('[TWEET id="1234567890" /]', $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testPreservesOtherContentUnchanged(): void
    {
        $input = "Some text before.\n\n[tweet id=\"1234567890\" /]\n\nSome text after.";

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('Some text before', $result);
        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
        $this->assertStringContainsString('Some text after', $result);
    }

    public function testHandlesMultipleTweetShortcodes(): void
    {
        $input = '[tweet id="111" /]\n[tweet id="222" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/111', $result);
        $this->assertStringContainsString('twitter.com/i/web/status/222', $result);
    }

    public function testSupportsSingleQuotes(): void
    {
        $result = $this->processor->process("[tweet id='1234567890' /]", $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testSupportsUnquotedValues(): void
    {
        $result = $this->processor->process('[tweet id=1234567890 /]', $this->createEntry());

        $this->assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testHeadAssetsEmptyWhenNoTweets(): void
    {
        $content = '<p>No tweets here.</p>';

        assertSame('', $this->processor->headAssets($content));
    }

    public function testHeadAssetsInjectsWidgetScriptWhenTweetPresent(): void
    {
        $content = '<div class="shortcode shortcode-tweet"><blockquote></blockquote></div>';

        $headAssets = $this->processor->headAssets($content);

        $this->assertStringContainsString('platform.twitter.com/widgets.js', $headAssets);
        $this->assertStringContainsString('<script', $headAssets);
        $this->assertStringContainsString('async', $headAssets);
    }

    public function testAssetFilesIsEmpty(): void
    {
        assertSame([], $this->processor->assetFiles());
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_tweet_test_');
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
