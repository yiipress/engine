<?php

declare(strict_types=1);

namespace App\Processor\Shortcode;

use App\Content\Model\Entry;
use App\Processor\ContentProcessorInterface;

/**
 * Expands Vimeo shortcodes into embed HTML before markdown processing.
 *
 * Transforms:
 *   [vimeo id="VIDEO_ID" /]
 *   [vimeo id="VIDEO_ID" width="640" height="360" /]
 *
 * Into responsive embed HTML with lazy-loaded iframe.
 */
final readonly class VimeoProcessor implements ContentProcessorInterface
{
    use ParsesShortcodeAttributesTrait;

    private const SHORTCODE_PATTERN = '/\[vimeo\s+([^\]]+)\s*\/?\]/i';

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

                $width = isset($attributes['width']) ? (int) $attributes['width'] : 560;
                $height = isset($attributes['height']) ? (int) $attributes['height'] : 315;

                return $this->generateEmbedCode($videoId, $width, $height);
            },
            $content,
        );
    }

    private function generateEmbedCode(string $videoId, int $width, int $height): string
    {
        $escapedId = htmlspecialchars($videoId, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<div class="shortcode shortcode-vimeo">' .
            '<div class="video-container">' .
            '<iframe ' .
            'src="https://player.vimeo.com/video/%s?dnt=1" ' .
            'width="%d" height="%d" ' .
            'frameborder="0" ' .
            'allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media" ' .
            'allowfullscreen ' .
            'loading="lazy" ' .
            'title="Vimeo video player">' .
            '</iframe>' .
            '</div>' .
            '</div>',
            $escapedId,
            $width,
            $height,
        );
    }
}
