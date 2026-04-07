<?php

declare(strict_types=1);

namespace App\Processor\OEmbed;

/**
 * Interface for oEmbed processors that convert URLs to embedded content.
 *
 * Implementations handle specific URL patterns (e.g., YouTube, Twitter, Vimeo)
 * and generate corresponding embed HTML.
 */
interface OEmbedInterface
{
    /**
     * Check if there is support of the given URL.
     *
     * @param string $url The URL to check.
     * @return bool If the given URL is supported.
     */
    public function supportsOEmbed(string $url): bool;

    /**
     * Convert a URL to its embedded HTML representation.
     *
     * @param string $url The URL to convert
     * @return string|null The embed HTML, or null if the URL cannot be processed.
     */
    public function replaceOEmbed(string $url): ?string;
}
