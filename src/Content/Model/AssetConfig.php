<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

final readonly class AssetConfig
{
    public function __construct(
        public bool $fingerprint = true,
    ) {}
}
