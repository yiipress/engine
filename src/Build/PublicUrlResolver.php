<?php

declare(strict_types=1);

namespace YiiPress\Build;

use YiiPress\Content\Model\SiteConfig;

final class PublicUrlResolver
{
    public static function browserUrl(SiteConfig $siteConfig, string $url): string
    {
        return UrlResolver::browserUrl($siteConfig, $url);
    }

    public static function absoluteUrl(SiteConfig $siteConfig, string $url): string
    {
        return UrlResolver::absoluteUrl($siteConfig, $url);
    }

    public static function isSamePublicUrl(SiteConfig $siteConfig, string $sourcePermalink, string $targetUrl): bool
    {
        return UrlResolver::isSamePublicUrl($siteConfig, $sourcePermalink, $targetUrl);
    }
}
