<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class SiteConfig
{
    /**
     * @param list<string> $taxonomies
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $baseUrl,
        public string $language,
        public string $charset,
        public string $defaultAuthor,
        public string $dateFormat,
        public int $entriesPerPage,
        public string $permalink,
        public array $taxonomies,
        public array $params,
        public MarkdownConfig $markdown = new MarkdownConfig(),
        public string $theme = '',
        public string $highlightTheme = '',
        public string $image = '',
        public string $twitterSite = '',
        public RobotsTxtConfig $robotsTxt = new RobotsTxtConfig(),
        public bool $toc = true,
        public ?SearchConfig $search = null,
        public AssetConfig $assets = new AssetConfig(),
        public ?RelatedConfig $related = null,
    ) {}
}
