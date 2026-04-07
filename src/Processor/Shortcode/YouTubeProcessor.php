<?php

declare(strict_types=1);

namespace App\Processor\Shortcode;

use App\Content\Model\Entry;
use App\Processor\ContentProcessorInterface;
use App\Processor\OEmbed\OEmbedInterface;

use function htmlspecialchars;
use function is_int;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function strtolower;
use function trim;

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
final readonly class YouTubeProcessor implements ContentProcessorInterface, OEmbedInterface
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
                $width = isset($attributes['width']) ? (int) $attributes['width'] : 560;
                $height = isset($attributes['height']) ? (int) $attributes['height'] : 315;

                return $this->generateEmbedCode($videoId, $width, $height, $start);
            },
            $content,
        );
    }

    public function supportsOEmbed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match ($host) {
            'youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com' => true,
            default => false,
        };
    }

    public function replaceOEmbed(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $videoId = null;
        $start = 0;

        if (preg_match('~^/(?:watch)?$~', $path) === 1) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $value = $params['v'] ?? null;
            if (is_string($value) && $value !== '') {
                $videoId = $value;
            }
            $start = $this->extractStart($params);
        } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{6,})/?$~', $path, $matches) === 1) {
            $videoId = $matches[1];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $start = $this->extractStart($params);
        } elseif (preg_match('~^/([A-Za-z0-9_-]{6,})/?$~', $path, $matches) === 1) {
            $videoId = $matches[1];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
            $start = $this->extractStart($params);
        }

        if ($videoId === null || $videoId === '') {
            return null;
        }

        return $this->generateEmbedCode($videoId, 560, 315, $start);
    }

    private function generateEmbedCode(string $videoId, int $width, int $height, int $start = 0): string
    {
        $escapedId = htmlspecialchars($videoId, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $startParam = $start > 0 ? '?start=' . $start : '';

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

    /**
     * @param array<string, mixed> $params
     */
    private function extractStart(array $params): int
    {
        $value = $params['start'] ?? $params['t'] ?? null;

        if (!is_string($value) && !is_int($value)) {
            return 0;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return 0;
        }

        if (preg_match('/^\d+$/', $normalized) === 1) {
            return (int) $normalized;
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $normalized, $matches) !== 1) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);
    }
}
