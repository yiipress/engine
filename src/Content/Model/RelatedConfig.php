<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class RelatedConfig
{
    public function __construct(
        public int $limit = 5,
        public int $tagWeight = 2,
        public int $categoryWeight = 3,
        public bool $sameCollectionOnly = true,
    ) {}
}
