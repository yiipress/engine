<?php

declare(strict_types=1);

namespace YiiPress\Build;

final readonly class SiteCheckIssue
{
    public function __construct(
        public string $filePath,
        public string $target,
        public string $message,
    ) {}
}
