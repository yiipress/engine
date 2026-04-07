<?php

declare(strict_types=1);

namespace App\Processor\OEmbed;

use App\Content\Model\Entry;
use App\Processor\ContentProcessorInterface;

use function preg_replace_callback;

/**
 * Expands standalone provider URLs into embed HTML before markdown processing.
 */
final readonly class OEmbedProcessor implements ContentProcessorInterface
{
    private const string URL_LINE_PATTERN = '/^(?<indent>[ \t]*)(?<url>https?:\/\/[^\s<>()]+)[ \t]*$/mi';

    /** @var list<OEmbedInterface> */
    private array $providers;

    public function __construct(
        OEmbedInterface ...$providers,
    ) {
        $this->providers = $providers;
    }

    public function process(string $content, Entry $entry): string
    {
        return (string) preg_replace_callback(
            self::URL_LINE_PATTERN,
            function (array $matches): string {
                $embed = $this->embedForUrl($matches['url']);

                if ($embed === null) {
                    return $matches[0];
                }

                return $matches['indent'] . $embed;
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
