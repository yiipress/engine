<?php

declare(strict_types=1);

namespace App\Build;

final readonly class MetaTags
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $type,        // 'article' or 'website'
        public string $image,       // absolute URL or empty
        public string $twitterCard, // 'summary_large_image' or 'summary'
        public string $twitterSite, // '@handle' or empty
    ) {}
}
