<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use App\Content\Model\SiteConfig;
use App\Content\Model\Translation;

use function ltrim;
use function rtrim;
use function str_starts_with;

final class MetaTagsBuilder
{
    /**
     * @param list<Translation> $translations alternates for the current entry
     */
    public static function forEntry(SiteConfig $siteConfig, Entry $entry, string $permalink, array $translations = []): MetaTags
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
            alternateLanguages: self::buildAlternates($siteConfig, $entry, $permalink, $translations),
        );
    }

    /**
     * @param list<Translation> $translations
     * @return array<string, string>
     */
    private static function buildAlternates(SiteConfig $siteConfig, Entry $entry, string $permalink, array $translations): array
    {
        if ($translations === [] || $siteConfig->i18n === null) {
            return [];
        }

        $currentLanguage = $entry->language !== '' ? $entry->language : $siteConfig->i18n->defaultLanguage;
        $alternates = [$currentLanguage => self::absoluteUrl($siteConfig->baseUrl, $permalink)];

        foreach ($translations as $translation) {
            $alternates[$translation->language] = self::absoluteUrl($siteConfig->baseUrl, $translation->permalink);
        }

        if (isset($alternates[$siteConfig->i18n->defaultLanguage])) {
            $alternates['x-default'] = $alternates[$siteConfig->i18n->defaultLanguage];
        }

        return $alternates;
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
