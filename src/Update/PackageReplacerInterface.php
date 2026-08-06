<?php

declare(strict_types=1);

namespace YiiPress\Update;

interface PackageReplacerInterface
{
    public function replace(string $temporaryPath, string $targetPath): void;
}
