<?php

declare(strict_types=1);

namespace YiiPress\Processor\OEmbed;

use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorInterface;

use function base64_decode;
use function base64_encode;
use function preg_replace_callback;
use function str_contains;

/**
 * Preserves standalone provider URLs before Markdown and renders their embed HTML afterwards.
 */
final readonly class OEmbedProcessor implements ContentProcessorInterface
{
    private const string MARKER_PREFIX = '<!-- yiipress-oembed:';
    private const string MARKER_PATTERN = '/<!-- yiipress-oembed:([A-Za-z0-9+\/=]+) -->/';
    private const string URL_LINE_PATTERN = '/^(?<indent>[ \t]*)(?<url>https?:\/\/[^\s<>()]+)[ \t]*$/mi';

    /** @var array<array-key, OEmbedInterface> */
    private array $providers;

    public function __construct(
        OEmbedInterface ...$providers,
    ) {
        $this->providers = $providers;
    }

    public function process(string $content, Entry $entry): string
    {
        if (str_contains($content, self::MARKER_PREFIX)) {
            return $this->renderEmbeds($content);
        }

        if (!str_contains($content, 'http://') && !str_contains($content, 'https://')) {
            return $content;
        }

        return (string) preg_replace_callback(
            self::URL_LINE_PATTERN,
            function (array $matches): string {
                $embed = $this->embedForUrl($matches['url']);

                if ($embed === null) {
                    return $matches[0];
                }

                return $matches['indent'] . self::MARKER_PREFIX . base64_encode($embed) . ' -->';
            },
            $content,
        );
    }

    private function renderEmbeds(string $content): string
    {
        return (string) preg_replace_callback(
            self::MARKER_PATTERN,
            static function (array $matches): string {
                $embed = base64_decode($matches[1], true);

                return $embed === false ? $matches[0] : $embed;
            },
            $content,
        );
    }

    private function embedForUrl(string $url): ?string
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supportsOEmbed($url)) {
                continue;
            }

            $embed = $provider->replaceOEmbed($url);
            if ($embed !== null) {
                return $embed;
            }
        }

        return null;
    }
}
