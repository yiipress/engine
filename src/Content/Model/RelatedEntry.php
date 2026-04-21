<?php

declare(strict_types=1);

namespace App\Content\Model;

use DateTimeImmutable;

final readonly class RelatedEntry
{
    public function __construct(
        public string $title,
        public string $permalink,
        public ?DateTimeImmutable $date,
        public string $summary,
        public int $score,
    ) {}
}
