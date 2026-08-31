<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Processor;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorPipeline;
use YiiPress\Processor\MarkdownProcessor;
use YiiPress\Processor\OEmbed\OEmbedInterface;
use YiiPress\Processor\OEmbed\OEmbedProcessor;
use YiiPress\Processor\Shortcode\TweetProcessor;
use YiiPress\Processor\Shortcode\VimeoProcessor;
use YiiPress\Processor\Shortcode\YouTubeProcessor;
use YiiPress\Render\MarkdownRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class OEmbedProcessorTest extends TestCase
{
    private OEmbedProcessor $processor;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new OEmbedProcessor(
            new YouTubeProcessor(),
            new VimeoProcessor(),
            new TweetProcessor(),
        );
    }

    public function testConvertsStandaloneYouTubeWatchUrlToEmbed(): void
    {
        $result = $this->render("https://www.youtube.com/watch?v=dQw4w9WgXcQ");

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
        assertStringContainsString('class="shortcode shortcode-youtube"', $result);
    }

    public function testKeepsYouTubeIframeUnescapedAfterMarkdownProcessing(): void
    {
        $pipeline = new ContentProcessorPipeline(
            $this->processor,
            new MarkdownProcessor(new MarkdownRenderer()),
            $this->processor,
        );

        $result = $pipeline->process(
            'https://www.youtube.com/watch?v=_kOlTvH8zGQ',
            $this->createEntry(),
        );

        assertStringContainsString('<iframe ', $result);
        assertStringNotContainsString('&lt;iframe', $result);
    }

    public function testConvertsShortYouTubeUrlToEmbed(): void
    {
        $result = $this->render("https://youtu.be/dQw4w9WgXcQ");

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    public function testConvertsYouTubeStartTimeToEmbedParam(): void
    {
        $result = $this->render("https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s");

        assertStringContainsString('?start=90', $result);
    }

    public function testConvertsStandaloneVimeoUrlToEmbed(): void
    {
        $result = $this->render("https://vimeo.com/123456789");

        assertStringContainsString('player.vimeo.com/video/123456789', $result);
        assertStringContainsString('class="shortcode shortcode-vimeo"', $result);
    }

    public function testConvertsStandaloneTwitterUrlToEmbed(): void
    {
        $result = $this->render("https://twitter.com/samdark/status/1234567890");

        assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
        assertStringContainsString('class="shortcode shortcode-tweet"', $result);
    }

    public function testConvertsStandaloneXUrlToEmbed(): void
    {
        $result = $this->render("https://x.com/samdark/status/1234567890");

        assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testLeavesInlineUrlsUnchanged(): void
    {
        $input = 'Watch https://youtu.be/dQw4w9WgXcQ later.';

        $result = $this->render($input);

        assertSame($input, $result);
    }

    public function testSkipsRegexWorkWhenContentHasNoHttpMarker(): void
    {
        $input = 'Standalone text without URLs.';

        assertSame($input, $this->processor->process($input, $this->createEntry()));
    }

    public function testLeavesUnsupportedProvidersUnchanged(): void
    {
        $input = 'https://example.com/video/123';

        $result = $this->render($input);

        assertSame($input, $result);
    }

    public function testHandlesMultipleEmbeds(): void
    {
        $input = "https://youtu.be/dQw4w9WgXcQ\n\nhttps://vimeo.com/123456789";

        $result = $this->render($input);

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
        assertStringContainsString('player.vimeo.com/video/123456789', $result);
    }

    public function testPreservesSurroundingIndentation(): void
    {
        $input = "  https://youtu.be/dQw4w9WgXcQ";

        $result = $this->render($input);

        assertStringNotContainsString("\n", $result);
        assertStringContainsString('  <div class="shortcode shortcode-youtube">', $result);
    }

    public function testSupportsCustomProvidersViaInterface(): void
    {
        $processor = new OEmbedProcessor(
            new class implements OEmbedInterface {
                public function supportsOEmbed(string $url): bool
                {
                    return $url === 'https://example.com/custom';
                }

                public function replaceOEmbed(string $url): ?string
                {
                    return '<div class="custom-embed">custom</div>';
                }
            },
        );

        $entry = $this->createEntry();
        $result = $processor->process("https://example.com/custom", $entry);
        $result = $processor->process($result, $entry);

        assertSame('<div class="custom-embed">custom</div>', $result);
    }

    private function render(string $content): string
    {
        $entry = $this->createEntry();

        return $this->processor->process($this->processor->process($content, $entry), $entry);
    }

    private function createEntry(): Entry
    {
        $tmp = tempnam(sys_get_temp_dir(), 'yiipress_oembed_test_');
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

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
