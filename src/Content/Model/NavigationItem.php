<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class NavigationItem
{
    /**
     * @param list<NavigationItem> $children
     */
    public function __construct(
        public string $title,
        public string $url,
        public array $children,
    ) {}
}
