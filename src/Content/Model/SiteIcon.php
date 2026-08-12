<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

final readonly class SiteIcon
{
    public function __construct(
        public string $path,
        public string $type,
    ) {}
}
