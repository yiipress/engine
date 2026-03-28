<?php

declare(strict_types=1);

namespace App\Processor\Shortcode;

use App\Content\Model\Entry;
use App\Processor\ContentProcessorInterface;

/**
 * Expands YouTube shortcodes into embed HTML before markdown processing.
 *
 * Transforms:
 *   [youtube id="VIDEO_ID" /]
 *   [youtube id="VIDEO_ID" start="30" /]
 *   [youtube id="VIDEO_ID" width="640" height="360" /]
 *
 * Into responsive embed HTML with lazy-loaded iframe.
 */
final readonly class YouTubeProcessor implements ContentProcessorInterface
{
    use ParsesShortcodeAttributesTrait;

    private const string SHORTCODE_PATTERN = '/\[youtube\s+([^]]+)\s*\/?]/i';

    public function process(string $content, Entry $entry): string
    {
        return (string) preg_replace_callback(
            self::SHORTCODE_PATTERN,
            function (array $matches): string {
                $attributes = $this->parseAttributes($matches[1]);
                $videoId = $attributes['id'] ?? '';

                if ($videoId === '') {
                    return $matches[0];
                }

                $start = isset($attributes['start']) ? (int) $attributes['start'] : 0;
                $startParam = $start > 0 ? '?start=' . $start : '';
                $width = isset($attributes['width']) ? (int) $attributes['width'] : 560;
                $height = isset($attributes['height']) ? (int) $attributes['height'] : 315;

                return $this->generateEmbedCode($videoId, $startParam, $width, $height);
            },
            $content,
        );
    }

    private function generateEmbedCode(string $videoId, string $startParam, int $width, int $height): string
    {
        $escapedId = htmlspecialchars($videoId, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<div class="shortcode shortcode-youtube">' .
            '<div class="video-container">' .
            '<iframe ' .
            'src="https://www.youtube.com/embed/%s%s" ' .
            'width="%d" height="%d" ' .
            'frameborder="0" ' .
            'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ' .
            'allowfullscreen ' .
            'loading="lazy" ' .
            'title="YouTube video player">' .
            '</iframe>' .
            '</div>' .
            '</div>',
            $escapedId,
            $startParam,
            $width,
            $height,
        );
    }
}
