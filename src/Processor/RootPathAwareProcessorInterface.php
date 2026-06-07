<?php

declare(strict_types=1);

namespace YiiPress\Processor;

interface RootPathAwareProcessorInterface
{
    public function applyRootPath(string $rootPath): void;
}
