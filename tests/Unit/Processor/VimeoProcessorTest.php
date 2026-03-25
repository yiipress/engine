<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\Shortcode\VimeoProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class VimeoProcessorTest extends TestCase
{
    private VimeoProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new VimeoProcessor();
    }

    public function testConvertsVimeoShortcodeToEmbed(): void
    {
        $input = '[vimeo id="123456789" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $result);
        $this->assertStringContainsString('<iframe', $result);
        $this->assertStringContainsString('class="shortcode shortcode-vimeo"', $result);
        $this->assertStringContainsString('class="video-container"', $result);
    }

    public function testConvertsVimeoShortcodeWithoutSelfClosingSlash(): void
    {
        $input = '[vimeo id="123456789"]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $result);
    }

    public function testVimeoEmbedHasDoNotTrackParam(): void
    {
        $input = '[vimeo id="123456789" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('?dnt=1', $result);
    }

    public function testReturnsUnchangedWhenIdMissing(): void
    {
        $input = '[vimeo /]';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame('[vimeo /]', $result);
    }

    public function testEscapesHtmlInVideoId(): void
    {
        $input = '[vimeo id="<script>alert(1)</script>" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testCaseInsensitive(): void
    {
        $input = '[VIMEO id="test123" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('vimeo.com/video/test123', $result);
    }

    public function testEmbedHasFullscreenSupport(): void
    {
        $result = $this->processor->process('[vimeo id="test" /]', $this->createEntry());

        $this->assertStringContainsString('allowfullscreen', $result);
    }

    public function testEmbedHasLazyLoading(): void
    {
        $result = $this->processor->process('[vimeo id="test" /]', $this->createEntry());

        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function testEmbedHasTitleAttribute(): void
    {
        $result = $this->processor->process('[vimeo id="test" /]', $this->createEntry());

        $this->assertStringContainsString('title="Vimeo video player"', $result);
    }

    public function testPreservesOtherContentUnchanged(): void
    {
        $input = "Some text before.\n\n[vimeo id=\"test123\" /]\n\nSome text after.";

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('Some text before', $result);
        $this->assertStringContainsString('vimeo.com/video/test123', $result);
        $this->assertStringContainsString('Some text after', $result);
    }

    public function testHandlesMultipleVimeoShortcodes(): void
    {
        $input = '[vimeo id="abc123" /]\n[vimeo id="def456" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('vimeo.com/video/abc123', $result);
        $this->assertStringContainsString('vimeo.com/video/def456', $result);
    }

    public function testSupportsSingleQuotes(): void
    {
        $input = "[vimeo id='test123' /]";

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('vimeo.com/video/test123', $result);
    }

    public function testSupportsUnquotedValues(): void
    {
        $input = '[vimeo id=test123 /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('vimeo.com/video/test123', $result);
    }

    public function testDefaultWidthAndHeight(): void
    {
        $result = $this->processor->process('[vimeo id="test123" /]', $this->createEntry());

        $this->assertStringContainsString('width="560"', $result);
        $this->assertStringContainsString('height="315"', $result);
    }

    public function testCustomWidthAndHeight(): void
    {
        $input = '[vimeo id="test123" width="640" height="360" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="640"', $result);
        $this->assertStringContainsString('height="360"', $result);
    }

    public function testOnlyCustomWidthUsesDefaultHeight(): void
    {
        $input = '[vimeo id="test123" width="800" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="800"', $result);
        $this->assertStringContainsString('height="315"', $result);
    }

    public function testOnlyCustomHeightUsesDefaultWidth(): void
    {
        $input = '[vimeo id="test123" height="480" /]';

        $result = $this->processor->process($input, $this->createEntry());

        $this->assertStringContainsString('width="560"', $result);
        $this->assertStringContainsString('height="480"', $result);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_vimeo_test_');
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
