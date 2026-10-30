<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

final readonly class Author
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $url = null,
    ) {}
}
