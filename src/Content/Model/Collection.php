<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class Collection
{
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
    ) {}
}
