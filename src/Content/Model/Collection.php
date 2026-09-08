<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

final readonly class Collection
{
    public const int DEFAULT_FEED_LIMIT = 20;
    /**
     * @param list<string> $order
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public string $permalink,
        public string $sortBy,
        public string $sortOrder,
        public int $entriesPerPage,
        public bool $feed,
        public bool $listing,
        public array $order = [],
        public bool $navigationPager = false,
        public int $feedLimit = self::DEFAULT_FEED_LIMIT,
    ) {}
}
