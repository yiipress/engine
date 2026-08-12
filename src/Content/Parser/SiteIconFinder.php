<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use YiiPress\Content\Model\SiteIcon;

final class SiteIconFinder
{
    /** @var array<string, string> */
    private const array TYPES = [
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    /**
     * @return list<SiteIcon>
     */
    public function find(string $contentDir): array
    {
        $icons = [];

        foreach (self::TYPES as $extension => $type) {
            $path = 'icon.' . $extension;
            if (is_file($contentDir . '/' . $path)) {
                $icons[] = new SiteIcon($path, $type);
            }
        }

        return $icons;
    }
}
