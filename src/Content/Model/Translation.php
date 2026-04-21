<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class Translation
{
    public function __construct(
        public string $language,
        public string $permalink,
        public string $title,
    ) {}
}
