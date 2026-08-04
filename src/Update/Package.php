<?php

declare(strict_types=1);

namespace YiiPress\Update;

final readonly class Package
{
    public function __construct(
        public string $targetPath,
        public string $assetName,
    ) {}
}
