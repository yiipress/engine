<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use App\Processor\Shortcode\TweetProcessor;
use App\Processor\Shortcode\VimeoProcessor;
use App\Processor\Shortcode\YouTubeProcessor;
use PHPUnit\Framework\TestCase;

final class OEmbedProviderTest extends TestCase
{
    public function testYouTubeProviderMatchesAndRendersEmbed(): void
    {
        $provider = new YouTubeProcessor();

        $this->assertTrue($provider->supportsOEmbed('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertStringContainsString(
            'youtube.com/embed/dQw4w9WgXcQ?start=90',
            (string) $provider->replaceOEmbed('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s'),
        );
    }

    public function testVimeoProviderMatchesAndRendersEmbed(): void
    {
        $provider = new VimeoProcessor();

        $this->assertTrue($provider->supportsOEmbed('https://vimeo.com/123456789'));
        $this->assertStringContainsString(
            'player.vimeo.com/video/123456789?dnt=1',
            (string) $provider->replaceOEmbed('https://vimeo.com/123456789'),
        );
    }

    public function testTweetProviderMatchesAndRendersEmbed(): void
    {
        $provider = new TweetProcessor();

        $this->assertTrue($provider->supportsOEmbed('https://x.com/samdark/status/1234567890'));
        $this->assertStringContainsString(
            'twitter.com/i/web/status/1234567890',
            (string) $provider->replaceOEmbed('https://x.com/samdark/status/1234567890'),
        );
    }

    public function testProvidersReturnNullForUnsupportedUrlShape(): void
    {
        $this->assertNull((new YouTubeProcessor())->replaceOEmbed('https://www.youtube.com/watch'));
        $this->assertNull((new VimeoProcessor())->replaceOEmbed('https://vimeo.com/channels/staffpicks/123456789'));
        $this->assertNull((new TweetProcessor())->replaceOEmbed('https://x.com/samdark'));
    }
}
