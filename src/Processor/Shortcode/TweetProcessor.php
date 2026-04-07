<?php

declare(strict_types=1);

namespace App\Processor\Shortcode;

use App\Content\Model\Entry;
use App\Processor\AssetProcessorInterface;
use App\Processor\ContentProcessorInterface;
use App\Processor\OEmbed\OEmbedInterface;

use function htmlspecialchars;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * Expands tweet shortcodes into Twitter embed HTML before markdown processing.
 *
 * Transforms:
 *   [tweet id="1234567890" /]
 *
 * Into a Twitter blockquote embed that the Twitter widget JS renders client-side.
 */
final readonly class TweetProcessor implements ContentProcessorInterface, AssetProcessorInterface, OEmbedInterface
{
    use ParsesShortcodeAttributesTrait;

    private const string SHORTCODE_PATTERN = '/\[tweet\s+([^]]+)\s*\/?]/i';
    private const string MARKER = 'shortcode-tweet';

    public function process(string $content, Entry $entry): string
    {
        return (string) preg_replace_callback(
            self::SHORTCODE_PATTERN,
            function (array $matches): string {
                $attributes = $this->parseAttributes($matches[1]);
                $tweetId = $attributes['id'] ?? '';

                if ($tweetId === '') {
                    return $matches[0];
                }

                return $this->generateEmbedCode($tweetId);
            },
            $content,
        );
    }

    public function headAssets(string $processedContent): string
    {
        if (!str_contains($processedContent, self::MARKER)) {
            return '';
        }

        return '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
    }

    public function supportsOEmbed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match ($host) {
            'twitter.com', 'www.twitter.com', 'x.com', 'www.x.com' => true,
            default => false,
        };
    }

    public function replaceOEmbed(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~^/[^/]+/status/(\d+)/?$~', $path, $matches) !== 1) {
            return null;
        }

        return $this->generateEmbedCode($matches[1]);
    }

    public function assetFiles(): array
    {
        return [];
    }

    private function generateEmbedCode(string $tweetId): string
    {
        $escapedId = htmlspecialchars($tweetId, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<div class="shortcode shortcode-tweet">' .
            '<blockquote class="twitter-tweet" data-dnt="true">' .
            '<a href="https://twitter.com/i/web/status/%s"></a>' .
            '</blockquote>' .
            '</div>',
            $escapedId,
        );
    }
}
