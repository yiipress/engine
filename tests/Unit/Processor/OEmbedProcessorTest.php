<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Content\Model\Entry;
use App\Processor\OEmbed\OEmbedInterface;
use App\Processor\OEmbed\OEmbedProcessor;
use App\Processor\Shortcode\TweetProcessor;
use App\Processor\Shortcode\VimeoProcessor;
use App\Processor\Shortcode\YouTubeProcessor;
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
        $result = $this->processor->process("https://www.youtube.com/watch?v=dQw4w9WgXcQ", $this->createEntry());

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
        assertStringContainsString('class="shortcode shortcode-youtube"', $result);
    }

    public function testConvertsShortYouTubeUrlToEmbed(): void
    {
        $result = $this->processor->process("https://youtu.be/dQw4w9WgXcQ", $this->createEntry());

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    public function testConvertsYouTubeStartTimeToEmbedParam(): void
    {
        $result = $this->processor->process("https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s", $this->createEntry());

        assertStringContainsString('?start=90', $result);
    }

    public function testConvertsStandaloneVimeoUrlToEmbed(): void
    {
        $result = $this->processor->process("https://vimeo.com/123456789", $this->createEntry());

        assertStringContainsString('player.vimeo.com/video/123456789', $result);
        assertStringContainsString('class="shortcode shortcode-vimeo"', $result);
    }

    public function testConvertsStandaloneTwitterUrlToEmbed(): void
    {
        $result = $this->processor->process("https://twitter.com/samdark/status/1234567890", $this->createEntry());

        assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
        assertStringContainsString('class="shortcode shortcode-tweet"', $result);
    }

    public function testConvertsStandaloneXUrlToEmbed(): void
    {
        $result = $this->processor->process("https://x.com/samdark/status/1234567890", $this->createEntry());

        assertStringContainsString('twitter.com/i/web/status/1234567890', $result);
    }

    public function testLeavesInlineUrlsUnchanged(): void
    {
        $input = 'Watch https://youtu.be/dQw4w9WgXcQ later.';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame($input, $result);
    }

    public function testLeavesUnsupportedProvidersUnchanged(): void
    {
        $input = 'https://example.com/video/123';

        $result = $this->processor->process($input, $this->createEntry());

        assertSame($input, $result);
    }

    public function testHandlesMultipleEmbeds(): void
    {
        $input = "https://youtu.be/dQw4w9WgXcQ\n\nhttps://vimeo.com/123456789";

        $result = $this->processor->process($input, $this->createEntry());

        assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $result);
        assertStringContainsString('player.vimeo.com/video/123456789', $result);
    }

    public function testPreservesSurroundingIndentation(): void
    {
        $input = "  https://youtu.be/dQw4w9WgXcQ";

        $result = $this->processor->process($input, $this->createEntry());

        assertStringNotContainsString("\n", $result);
        assertStringContainsString('  <div class="shortcode shortcode-youtube">', $result);
    }

    public function testSupportsCustomProvidersViaInterface(): void
    {
        $processor = new OEmbedProcessor(
            new class () implements OEmbedInterface {
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

        $result = $processor->process("https://example.com/custom", $this->createEntry());

        assertSame('<div class="custom-embed">custom</div>', $result);
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
