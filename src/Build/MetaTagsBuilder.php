<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;

use function ltrim;
use function rtrim;
use function str_starts_with;

final class MetaTagsBuilder
{
    public static function forEntry(SiteConfig $siteConfig, Entry $entry, string $permalink): MetaTags
    {
        $image = self::resolveImage($entry->image, $siteConfig);
        return new MetaTags(
            title: $entry->title,
            description: $entry->summary(),
            canonicalUrl: self::absoluteUrl($siteConfig->baseUrl, $permalink),
            type: 'article',
            image: $image,
            twitterCard: $image !== '' ? 'summary_large_image' : 'summary',
            twitterSite: $siteConfig->twitterSite,
        );
    }

    public static function forPage(SiteConfig $siteConfig, string $title, string $description, string $permalink): MetaTags
    {
        $image = self::resolveImage($siteConfig->image, $siteConfig);
        return new MetaTags(
            title: $title,
            description: $description,
            canonicalUrl: self::absoluteUrl($siteConfig->baseUrl, $permalink),
            type: 'website',
            image: $image,
            twitterCard: $image !== '' ? 'summary_large_image' : 'summary',
            twitterSite: $siteConfig->twitterSite,
        );
    }

    private static function resolveImage(string $image, SiteConfig $siteConfig): string
    {
        $resolved = $image !== '' ? $image : $siteConfig->image;
        if ($resolved === '') {
            return '';
        }
        if (str_starts_with($resolved, 'http://') || str_starts_with($resolved, 'https://')) {
            return $resolved;
        }
        return rtrim($siteConfig->baseUrl, '/') . '/' . ltrim($resolved, '/');
    }

    private static function absoluteUrl(string $baseUrl, string $permalink): string
    {
        if ($baseUrl === '' || $permalink === '') {
            return '';
        }
        return rtrim($baseUrl, '/') . $permalink;
    }
}
