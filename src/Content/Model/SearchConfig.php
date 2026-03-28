<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class SearchConfig
{
    public function __construct(
        public bool $fullText = false,
        public int $results = 10,
    ) {}
}
