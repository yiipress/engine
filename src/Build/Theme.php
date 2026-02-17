<?php

declare(strict_types=1);

namespace App\Build;

final readonly class Theme
{
    public function __construct(
        public string $name,
        public string $path,
    ) {}
}
