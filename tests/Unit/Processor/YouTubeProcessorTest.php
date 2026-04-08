<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\Shortcode\YouTubeProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class YouTubeProcessorTest extends TestCase
{
    private YouTubeProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new YouTubeProcessor();
    }

    public function testConvertsYouTubeShortcodeToEmbed(): void
    {
        $input = '[youtube id="dQw4w9WgXcQ" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
        $this->assertStringContainsString('<iframe', $result);
        $this->assertStringContainsString('class="shortcode shortcode-youtube"', $result);
        $this->assertStringContainsString('class="video-container"', $result);
    }

    public function testConvertsYouTubeShortcodeWithoutSelfClosingSlash(): void
    {
        $input = '[youtube id="dQw4w9WgXcQ"]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    public function testSkipsRegexWorkWhenContentHasNoYouTubeShortcodeMarker(): void
    {
        $input = 'Plain content without shortcode markers.';

        assertSame($input, $this->processor->process($input, $this->createEntry()));
    }

    public function testYouTubeShortcodeWithStartTime(): void
    {
        $input = '[youtube id="dQw4w9WgXcQ" start="30" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('?start=30', $result);
    }

    public function testYouTubeShortcodeWithZeroStartTimeOmitsParam(): void
    {
        $input = '[youtube id="dQw4w9WgXcQ" start="0" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringNotContainsString('?start=', $result);
    }

    public function testReturnsUnchangedWhenIdMissing(): void
    {
        $input = '[youtube /]';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame('[youtube /]', $result);
    }

    public function testEscapesHtmlInVideoId(): void
    {
        $input = '[youtube id="<script>alert(1)</script>" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testCaseInsensitive(): void
    {
        $input = '[YOUTUBE id="test123" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/test123', $result);
    }

    public function testEmbedHasFullscreenSupport(): void
    {
        $result = $this->processor->process('[youtube id="test" /]', $this->createEntry());

        $this->assertStringContainsString('allowfullscreen', $result);
    }

    public function testEmbedHasLazyLoading(): void
    {
        $result = $this->processor->process('[youtube id="test" /]', $this->createEntry());

        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function testEmbedHasTitleAttribute(): void
    {
        $result = $this->processor->process('[youtube id="test" /]', $this->createEntry());

        $this->assertStringContainsString('title="YouTube video player"', $result);
    }

    public function testPreservesOtherContentUnchanged(): void
    {
        $input = "Some text before.\n\n[youtube id=\"test123\" /]\n\nSome text after.";

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('Some text before', $result);
        $this->assertStringContainsString('youtube.com/embed/test123', $result);
        $this->assertStringContainsString('Some text after', $result);
    }

    public function testHandlesMultipleYouTubeShortcodes(): void
    {
        $input = '[youtube id="abc123" /]\n[youtube id="def456" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/abc123', $result);
        $this->assertStringContainsString('youtube.com/embed/def456', $result);
    }

    public function testSupportsSingleQuotes(): void
    {
        $input = "[youtube id='test123' /]";

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/test123', $result);
    }

    public function testSupportsUnquotedValues(): void
    {
        $input = '[youtube id=test123 /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('youtube.com/embed/test123', $result);
    }

    public function testDefaultWidthAndHeight(): void
    {
        $result = $this->processor->process('[youtube id="test123" /]', $this->createEntry());

        $this->assertStringContainsString('width="560"', $result);
        $this->assertStringContainsString('height="315"', $result);
    }

    public function testCustomWidthAndHeight(): void
    {
        $input = '[youtube id="test123" width="640" height="360" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="640"', $result);
        $this->assertStringContainsString('height="360"', $result);
    }

    public function testOnlyCustomWidthUsesDefaultHeight(): void
    {
        $input = '[youtube id="test123" width="800" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="800"', $result);
        $this->assertStringContainsString('height="315"', $result);
    }

    public function testOnlyCustomHeightUsesDefaultWidth(): void
    {
        $input = '[youtube id="test123" height="480" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="560"', $result);
        $this->assertStringContainsString('height="480"', $result);
    }

    public function testCustomDimensionsWithStartTime(): void
    {
        $input = '[youtube id="test123" start="30" width="640" height="360" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('?start=30', $result);
        $this->assertStringContainsString('width="640"', $result);
        $this->assertStringContainsString('height="360"', $result);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_youtube_test_');
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
