<?php

declare(strict_types=1);

namespace YiiPress\Content;

use Yiisoft\Strings\StringHelper;

final class Slugifier
{
    public static function slugify(string $title, ?int $maxLength = null, string $fallback = ''): string
    {
        $slug = StringHelper::lowercase($title);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');

        if ($maxLength !== null && StringHelper::length($slug) > $maxLength) {
            $slug = StringHelper::substring($slug, 0, $maxLength);
            $slug = rtrim($slug, '-');
        }

        return $slug !== '' ? $slug : $fallback;
    }
}
