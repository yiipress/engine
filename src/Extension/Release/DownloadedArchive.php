<?php

declare(strict_types=1);

namespace YiiPress\Extension\Release;

/**
 * A release archive saved to disk.
 */
final readonly class DownloadedArchive
{
    public function __construct(
        public string $file,
        public string $url,
        public string $sha256,
        public int $size,
    ) {}
}
